<?php
require_once '../includes/api_auth.php';
require_once '../includes/helpers.php';

$has_commission = column_exists($pdo, 'services', 'commission_pct');
$has_specimen = column_exists($pdo, 'appointments', 'specimen_code');
$has_referring = column_exists($pdo, 'appointments', 'referring_doctor_name');

$method = $_SERVER['REQUEST_METHOD'];

try {
switch ($method) {
    case 'GET':
        $date = sanitize_string($_GET['date'] ?? date('Y-m-d'));
        $params = [$date];
        $doctor_filter = "";

        if (isset($_SESSION['role']) && $_SESSION['role'] === 'doctor') {
            $doctor_filter = " AND a.doctor_id = ? ";
            $params[] = $_SESSION['doctor_id'];
        }

        $extra = '';
        if ($has_commission) $extra .= ', s.commission_pct';

        $soft = appointments_soft_delete_where($pdo);
        $stmt = $pdo->prepare("
            SELECT a.*, p.full_name as patient_name, p.phone as patient_phone, d.full_name as doctor_name, s.name as service_name, s.price as service_price, sd.name as direction_name, sg.name as group_name{$extra}
            FROM appointments a 
            JOIN patients p ON a.patient_id = p.id 
            LEFT JOIN doctors d ON a.doctor_id = d.id 
            JOIN services s ON a.service_id = s.id 
            LEFT JOIN service_directions sd ON s.direction_id = sd.id
            LEFT JOIN service_groups sg ON sd.group_id = sg.id
            WHERE a.appointment_date = ? $soft $doctor_filter
            ORDER BY a.appointment_time ASC");
        $stmt->execute($params);
        api_success($stmt->fetchAll());
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        validate_required($data, ['patient_id', 'service_id', 'appointment_date', 'appointment_time']);

        // Conflict Validation
        if (!empty($data['doctor_id'])) {
            $receipt_id = !empty($data['receipt_id']) ? sanitize_string($data['receipt_id']) : null;

            $soft = appointments_soft_delete_where_simple($pdo);
            $check_query = "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND id != ?" . $soft;
            $check_params = [(int)$data['doctor_id'], sanitize_string($data['appointment_date']), sanitize_string($data['appointment_time']), $data['id'] ?? 0];

            if ($receipt_id) {
                $check_query .= " AND receipt_id != ?";
                $check_params[] = $receipt_id;
            }

            $check = $pdo->prepare($check_query);
            $check->execute($check_params);
            if ($check->fetch()) {
                api_error('Врач уже занят на это время');
            }
        }

        $receipt_id = !empty($data['receipt_id']) ? sanitize_string($data['receipt_id']) : null;
        $quantity = max(1, sanitize_int($data['quantity'] ?? 1));
        $referring_doctor_name = ($has_referring && !empty($data['referring_doctor_name'])) ? sanitize_string($data['referring_doctor_name']) : null;

        if (isset($data['id'])) {
            if ($has_referring) {
                $stmt = $pdo->prepare("UPDATE appointments SET patient_id = ?, receipt_id = ?, doctor_id = ?, referring_doctor_name = ?, service_id = ?, quantity = ?, appointment_date = ?, appointment_time = ?, status = ?, payment_status = ? WHERE id = ?");
                $stmt->execute([(int)$data['patient_id'], $receipt_id, $data['doctor_id'] ?: null, $referring_doctor_name, (int)$data['service_id'], $quantity, sanitize_string($data['appointment_date']), sanitize_string($data['appointment_time']), sanitize_string($data['status'] ?? 'scheduled'), sanitize_string($data['payment_status'] ?? 'unpaid'), $data['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE appointments SET patient_id = ?, receipt_id = ?, doctor_id = ?, service_id = ?, quantity = ?, appointment_date = ?, appointment_time = ?, status = ?, payment_status = ? WHERE id = ?");
                $stmt->execute([(int)$data['patient_id'], $receipt_id, $data['doctor_id'] ?: null, (int)$data['service_id'], $quantity, sanitize_string($data['appointment_date']), sanitize_string($data['appointment_time']), sanitize_string($data['status'] ?? 'scheduled'), sanitize_string($data['payment_status'] ?? 'unpaid'), $data['id']]);
            }
            $returnId = $data['id'];
            audit_log($pdo, 'update', 'appointment', $returnId);
        } else {
            $q_stmt = $pdo->prepare("SELECT direction_id FROM services WHERE id = ?");
            $q_stmt->execute([(int)$data['service_id']]);
            $direction_id = $q_stmt->fetchColumn();

            $queue_num = 0;
            if ($direction_id) {
                $max_q = $pdo->prepare("
                    SELECT MAX(a.queue_number) 
                    FROM appointments a
                    JOIN services s ON a.service_id = s.id
                    WHERE s.direction_id = ? AND a.appointment_date = ?
                ");
                $max_q->execute([$direction_id, sanitize_string($data['appointment_date'])]);
                $queue_num = (int)$max_q->fetchColumn() + 1;
            }

            $receipt_num = null;
            if ($receipt_id) {
                $rn_stmt = $pdo->prepare("SELECT MAX(receipt_number) FROM appointments WHERE receipt_id = ?");
                $rn_stmt->execute([$receipt_id]);
                $receipt_num = (int)$rn_stmt->fetchColumn() + 1;
            }

            $specimen_code = null;
            $is_lab_service = false;
            if ($has_specimen && !empty($data['doctor_id'])) {
                $lab_check = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM specialties sp
                    JOIN doctors d ON d.specialty_id = sp.id
                    WHERE d.id = ? AND (sp.name = 'Лаборатория' OR sp.name = 'laboratory')
                ");
                $lab_check->execute([(int)$data['doctor_id']]);
                $is_lab_service = (int)$lab_check->fetchColumn() > 0;
            }

            if ($has_specimen && $is_lab_service) {
                $today = sanitize_string($data['appointment_date']);
                $sc_stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ?");
                $sc_stmt->execute([$today]);
                $sc_count = (int)$sc_stmt->fetchColumn() + 1;
                $specimen_code = 'LAB-' . str_replace('-', '', $today) . '-' . str_pad($sc_count, 3, '0', STR_PAD_LEFT);
            }

            $cols = ['patient_id', 'receipt_id', 'receipt_number', 'doctor_id', 'service_id', 'quantity', 'appointment_date', 'appointment_time', 'status', 'payment_status', 'queue_number'];
            $vals = [(int)$data['patient_id'], $receipt_id, $receipt_num, $data['doctor_id'] ?: null, (int)$data['service_id'], $quantity, sanitize_string($data['appointment_date']), sanitize_string($data['appointment_time']), sanitize_string($data['status'] ?? 'scheduled'), sanitize_string($data['payment_status'] ?? 'unpaid'), $queue_num];

            if ($has_referring) {
                $cols[] = 'referring_doctor_name';
                $vals[] = $referring_doctor_name;
            }
            if ($has_specimen) {
                $cols[] = 'specimen_code';
                $vals[] = $specimen_code;
            }

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $stmt = $pdo->prepare("INSERT INTO appointments (" . implode(',', $cols) . ") VALUES ($placeholders)");
            $stmt->execute($vals);
            $returnId = $pdo->lastInsertId();
            audit_log($pdo, 'create', 'appointment', $returnId);

            // Telegram: notify admin about new service
            if (defined('TG_BOT_TOKEN') && TG_BOT_TOKEN) {
                require_once __DIR__ . '/telegram_bot.php';
                $svc_stmt = $pdo->prepare("SELECT s.name, s.price FROM services s WHERE s.id = ?");
                $svc_stmt->execute([(int)$data['service_id']]);
                $svc = $svc_stmt->fetch();
                $pat_stmt = $pdo->prepare("SELECT full_name FROM patients WHERE id = ?");
                $pat_stmt->execute([(int)$data['patient_id']]);
                $pat = $pat_stmt->fetch();
                notifyAdminNewService([
                    'service' => $svc['name'] ?? '',
                    'price' => $svc['price'] ?? 0,
                    'quantity' => $quantity,
                    'patient' => $pat['full_name'] ?? '',
                    'doctor' => null,
                    'receipt_id' => $receipt_id,
                ], $pdo);

                // Telegram: notify patient about new appointment
                $doc_name = null;
                if (!empty($data['doctor_id'])) {
                    $doc_stmt = $pdo->prepare("SELECT full_name FROM doctors WHERE id = ?");
                    $doc_stmt->execute([(int)$data['doctor_id']]);
                    $doc_name = $doc_stmt->fetchColumn();
                }
                notifyPatientAppointment((int)$data['patient_id'], [
                    'doctor' => $doc_name,
                    'service' => $svc['name'] ?? '',
                    'date' => $data['appointment_date'] ?? '',
                    'time' => $data['appointment_time'] ?? '',
                ], $pdo);
            }
        }
        api_success(['id' => $returnId]);
        break;

    case 'DELETE':
        require_admin();
        $id = sanitize_int($_GET['id'] ?? null);
        if (!$id) api_error('Missing ID');

        // Soft delete
        try {
            $pdo->prepare("UPDATE appointments SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
        } catch (PDOException $e) {
            $pdo->prepare("DELETE FROM appointments WHERE id = ?")->execute([$id]);
        }
        audit_log($pdo, 'delete', 'appointment', $id);
        api_success();
        break;
}
} catch (PDOException $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) error_log($e->getMessage());
    api_error('Database error', 500);
}
