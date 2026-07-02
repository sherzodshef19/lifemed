<?php
require_once '../includes/api_auth.php';
require_role(['admin', 'doctor', 'cashier']);

$method = $_SERVER['REQUEST_METHOD'];

try {
switch ($method) {
    case 'GET':
        $limit = max(1, min(100, sanitize_int($_GET['limit'] ?? 30)));
        $page = max(1, sanitize_int($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $q = sanitize_string($_GET['q'] ?? '');

        $query = "SELECT r.*, t.title as template_title, d.full_name as doctor_name, p.full_name as patient_name, p.id as patient_id 
                  FROM lab_results r 
                  JOIN lab_templates t ON r.template_id = t.id 
                  LEFT JOIN doctors d ON r.doctor_id = d.id
                  JOIN patients p ON r.patient_id = p.id";
        $params = [];
        $where = [];

        if (isset($_GET['patient_id'])) {
            $where[] = "r.patient_id = ?";
            $params[] = (int)$_GET['patient_id'];
        }
        if (isset($_GET['doctor_id'])) {
            $where[] = "r.doctor_id = ?";
            $params[] = (int)$_GET['doctor_id'];
        }
        if (isset($_GET['id'])) {
            $where[] = "r.id = ?";
            $params[] = (int)$_GET['id'];
        }
        if (isset($_GET['template_id']) && $_GET['template_id'] !== '') {
            $where[] = "r.template_id = ?";
            $params[] = (int)$_GET['template_id'];
        }
        if ($q) {
            $where[] = "(p.full_name LIKE ? OR p.id = ? OR t.title LIKE ?)";
            $params[] = "%$q%";
            $params[] = $q;
            $params[] = "%$q%";
        }

        if ($where) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Separate COUNT query instead of deprecated SQL_CALC_FOUND_ROWS
        $countQuery = "SELECT COUNT(*) FROM lab_results r 
                       JOIN lab_templates t ON r.template_id = t.id 
                       LEFT JOIN doctors d ON r.doctor_id = d.id
                       JOIN patients p ON r.patient_id = p.id";
        if ($where) { $countQuery .= " WHERE " . implode(" AND ", $where); }
        $countParams = array_slice($params, 0, -2); // exclude LIMIT/OFFSET
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetchColumn();

        if (isset($_GET['id']) && !isset($_GET['q'])) {
            api_success($results[0] ?? null);
        } else {
            api_success([
                'data' => $results,
                'total' => (int)$totalCount,
                'pages' => ceil($totalCount / $limit),
                'page' => $page
            ]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        validate_required($data, ['result_data']);
        $doc_id = $data['doctor_id'] ?? ($_SESSION['doctor_id'] ?? null);

        if (isset($data['id'])) {
            $stmt = $pdo->prepare("UPDATE lab_results SET result_data = ?, doctor_id = ? WHERE id = ?");
            $stmt->execute([sanitize_string($data['result_data']), $doc_id, $data['id']]);
            audit_log($pdo, 'update', 'lab_result', $data['id']);
        } else {
            validate_required($data, ['patient_id', 'template_id']);

            // Doctors can only create lab results for today's appointments
            if ($_SESSION['role'] === 'doctor') {
                $today = date('Y-m-d');
                $chk = $pdo->prepare("SELECT id FROM appointments WHERE patient_id = ? AND doctor_id = ? AND appointment_date = ? LIMIT 1");
                $chk->execute([(int)$data['patient_id'], $_SESSION['doctor_id'], $today]);
                if (!$chk->fetch()) {
                    api_error('Нельзя создать анализ: у пациента нет приёма сегодня (' . $today . ')');
                }
            }

            $stmt = $pdo->prepare("INSERT INTO lab_results (patient_id, template_id, result_data, doctor_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([(int)$data['patient_id'], (int)$data['template_id'], sanitize_string($data['result_data']), $doc_id]);
            $newId = $pdo->lastInsertId();
            audit_log($pdo, 'create', 'lab_result', $newId);

            // Telegram: notify patient about new lab result
            if (defined('TG_BOT_TOKEN') && TG_BOT_TOKEN) {
                require_once __DIR__ . '/telegram_bot.php';
                notifyPatientLabResult((int)$data['patient_id'], $newId, $pdo);
            }

            api_success(['id' => $newId]);
        }
        api_success();
        break;

    case 'DELETE':
        require_role(['admin', 'doctor']);
        $id = sanitize_int($_GET['id']);
        if (!$id) api_error('Missing ID');

        $pdo->prepare("DELETE FROM lab_results WHERE id = ?")->execute([$id]);
        audit_log($pdo, 'delete', 'lab_result', $id);
        api_success();
        break;
}
} catch (PDOException $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) error_log($e->getMessage());
    api_error('Database error', 500);
}
