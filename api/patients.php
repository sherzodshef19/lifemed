<?php
require_once '../includes/api_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
switch ($method) {
    case 'GET':
        if (isset($_GET['id']) && $_GET['id'] !== '') {
            $soft = patients_soft_delete_where($pdo);
            $stmt = $pdo->prepare("SELECT id, full_name, phone, dob, address, registration_date FROM patients WHERE id = ?" . $soft);
            $stmt->execute([$_GET['id']]);
            $patient = $stmt->fetch();
            api_success($patient ?: null);
            break;
        }

        $q = sanitize_string($_GET['q'] ?? '');
        $page = max(1, sanitize_int($_GET['page'] ?? 1));
        $limit = max(1, min(100, sanitize_int($_GET['limit'] ?? 15)));
        $offset = ($page - 1) * $limit;

        $soft = patients_soft_delete_where($pdo);
        if ($q) {
            $stmt = $pdo->prepare("SELECT id, full_name, phone, dob, address, registration_date FROM patients WHERE 1=1" . $soft . " AND (full_name LIKE ? OR phone LIKE ? OR LPAD(id, 5, '0') LIKE ? OR CAST(id AS CHAR) LIKE ?) ORDER BY full_name ASC LIMIT ? OFFSET ?");
            $stmt->execute(["%$q%", "%$q%", "%$q%", "%$q%", $limit, $offset]);
            $data = $stmt->fetchAll();

            $cnt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE 1=1" . $soft . " AND (full_name LIKE ? OR phone LIKE ? OR LPAD(id, 5, '0') LIKE ? OR CAST(id AS CHAR) LIKE ?)");
            $cnt->execute(["%$q%", "%$q%", "%$q%", "%$q%"]);
            $totalCount = $cnt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT id, full_name, phone, dob, address, registration_date FROM patients WHERE 1=1" . $soft . " ORDER BY registration_date DESC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $data = $stmt->fetchAll();

            $cnt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE 1=1" . $soft);
            $cnt->execute();
            $totalCount = $cnt->fetchColumn();
        }

        api_success([
            'data' => $data,
            'total' => (int)$totalCount,
            'pages' => ceil($totalCount / $limit),
            'page' => $page
        ]);
        break;

    case 'POST':
        require_any_role();
        $data = json_decode(file_get_contents('php://input'), true);
        validate_required($data, ['full_name', 'phone']);

        if (isset($data['id'])) {
            $stmt = $pdo->prepare("UPDATE patients SET full_name = ?, dob = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([sanitize_string($data['full_name']), sanitize_string($data['dob'] ?? ''), sanitize_string($data['phone']), sanitize_string($data['address'] ?? ''), $data['id']]);
            audit_log($pdo, 'update', 'patient', $data['id']);
            api_success();
        } else {
            $soft = patients_soft_delete_where($pdo);
            $stmt = $pdo->prepare("SELECT id, full_name, phone, dob, address FROM patients WHERE phone = ? AND full_name = ?" . $soft);
            $stmt->execute([sanitize_string($data['phone']), sanitize_string($data['full_name'])]);
            $existing = $stmt->fetch();

            if ($existing) {
                api_success(['id' => $existing['id'], 'patient' => $existing, 'already_exists' => true]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO patients (full_name, dob, phone, address) VALUES (?, ?, ?, ?)");
                $stmt->execute([sanitize_string($data['full_name']), sanitize_string($data['dob'] ?? ''), sanitize_string($data['phone']), sanitize_string($data['address'] ?? '')]);
                $newId = $pdo->lastInsertId();
                audit_log($pdo, 'create', 'patient', $newId);
                api_success([
                    'id' => $newId,
                    'patient' => [
                        'id' => $newId,
                        'full_name' => sanitize_string($data['full_name']),
                        'phone' => sanitize_string($data['phone']),
                        'dob' => sanitize_string($data['dob'] ?? ''),
                        'address' => sanitize_string($data['address'] ?? '')
                    ]
                ]);
            }
        }
        break;

    case 'DELETE':
        require_admin();
        $id = sanitize_int($_GET['id'] ?? null);
        if (!$id) api_error('Missing ID');

        try {
            $pdo->prepare("UPDATE patients SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
        } catch (PDOException $e) {
            $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$id]);
        }
        audit_log($pdo, 'delete', 'patient', $id);
        api_success();
        break;
}
} catch (PDOException $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) error_log($e->getMessage());
    api_error('Database error', 500);
}
