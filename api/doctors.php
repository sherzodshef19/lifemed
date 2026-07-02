<?php
require_once '../includes/api_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            try {
                $stmt = $pdo->query("
                    SELECT d.id, d.full_name, d.phone, d.username, d.specialty_id, 
                           sp.name as specialty_name, GROUP_CONCAT(srv.id) as service_ids 
                    FROM doctors d 
                    LEFT JOIN specialties sp ON d.specialty_id = sp.id
                    LEFT JOIN doctor_services ds ON d.id = ds.doctor_id 
                    LEFT JOIN services srv ON ds.service_id = srv.id 
                    GROUP BY d.id
                    ORDER BY d.full_name ASC
                ");
                $doctors = $stmt->fetchAll();
                foreach ($doctors as &$doctor) {
                    $doctor['service_ids'] = $doctor['service_ids'] ? explode(',', $doctor['service_ids']) : [];
                }
            } catch (PDOException $e) {
                $stmt = $pdo->query("
                    SELECT d.id, d.full_name, d.phone, d.username, d.specialty_id,
                           sp.name as specialty_name
                    FROM doctors d 
                    LEFT JOIN specialties sp ON d.specialty_id = sp.id
                    ORDER BY d.full_name ASC
                ");
                $doctors = $stmt->fetchAll();
                foreach ($doctors as &$doctor) {
                    $doctor['service_ids'] = [];
                }
            }
            api_success($doctors);
            break;

        case 'POST':
            require_admin_or_cashier();
            $data = json_decode(file_get_contents('php://input'), true);
            validate_required($data, ['full_name', 'username']);

            $pdo->beginTransaction();
            try {
                $spec_id = !empty($data['specialty_id']) ? (int)$data['specialty_id'] : null;

                if (isset($data['id'])) {
                    $stmt = $pdo->prepare("UPDATE doctors SET full_name = ?, phone = ?, username = ?, specialty_id = ? WHERE id = ?");
                    $stmt->execute([sanitize_string($data['full_name']), sanitize_string($data['phone'] ?? ''), sanitize_string($data['username']), $spec_id, $data['id']]);
                    $doctor_id = $data['id'];

                    if (!empty($data['password'])) {
                        $pw_error = validate_password($data['password']);
                        if ($pw_error) {
                            $pdo->rollBack();
                            api_error($pw_error);
                        }
                        $pdo->prepare("UPDATE doctors SET password = ? WHERE id = ?")->execute([password_hash($data['password'], PASSWORD_DEFAULT), $doctor_id]);
                    }
                    audit_log($pdo, 'update', 'doctor', $doctor_id);
                } else {
                    $pw_error = validate_password($data['password'] ?? '');
                    if ($pw_error) {
                        $pdo->rollBack();
                        api_error($pw_error);
                    }
                    // Check username uniqueness
                    $check = $pdo->prepare("SELECT id FROM doctors WHERE username = ?");
                    $check->execute([sanitize_string($data['username'])]);
                    if ($check->fetch()) {
                        $pdo->rollBack();
                        api_error('Username already exists');
                    }

                    $stmt = $pdo->prepare("INSERT INTO doctors (full_name, phone, username, password, specialty_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([sanitize_string($data['full_name']), sanitize_string($data['phone'] ?? ''), sanitize_string($data['username']), password_hash($data['password'], PASSWORD_DEFAULT), $spec_id]);
                    $doctor_id = $pdo->lastInsertId();
                    audit_log($pdo, 'create', 'doctor', $doctor_id);
                }

                // Sync services
                $pdo->prepare("DELETE FROM doctor_services WHERE doctor_id = ?")->execute([$doctor_id]);
                if (!empty($data['service_ids'])) {
                    $stmt = $pdo->prepare("INSERT INTO doctor_services (doctor_id, service_id) VALUES (?, ?)");
                    foreach ($data['service_ids'] as $sid) {
                        $stmt->execute([$doctor_id, (int)$sid]);
                    }
                }
                $pdo->commit();
                api_success();
            } catch (Exception $e) {
                $pdo->rollBack();
                api_error('Failed to save doctor');
            }
            break;

        case 'DELETE':
            require_admin();
            $id = sanitize_int($_GET['id'] ?? null);
            if (!$id) api_error('Missing ID');

            $pdo->prepare("DELETE FROM doctors WHERE id = ?")->execute([$id]);
            audit_log($pdo, 'delete', 'doctor', $id);
            api_success();
            break;
    }
} catch (PDOException $e) {
    api_error('Database error', 500);
}
