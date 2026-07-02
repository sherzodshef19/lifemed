<?php
require_once '../includes/api_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $group_id = sanitize_int($_GET['group_id'] ?? null);
            if ($group_id) {
                $stmt = $pdo->prepare("SELECT id, name, group_id FROM service_directions WHERE group_id = ? ORDER BY name ASC");
                $stmt->execute([$group_id]);
            } else {
                $stmt = $pdo->query("SELECT id, name, group_id FROM service_directions ORDER BY name ASC");
            }
            api_success($stmt->fetchAll());
            break;

        case 'POST':
            require_admin_or_cashier();
            $data = json_decode(file_get_contents('php://input'), true);
            validate_required($data, ['name', 'group_id']);

            if (!empty($data['id'])) {
                $stmt = $pdo->prepare("UPDATE service_directions SET name = ?, group_id = ? WHERE id = ?");
                $stmt->execute([sanitize_string($data['name']), (int)$data['group_id'], $data['id']]);
                audit_log($pdo, 'update', 'service_direction', $data['id']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO service_directions (name, group_id) VALUES (?, ?)");
                $stmt->execute([sanitize_string($data['name']), (int)$data['group_id']]);
                audit_log($pdo, 'create', 'service_direction', $pdo->lastInsertId());
            }
            api_success();
            break;

        case 'DELETE':
            require_admin();
            $id = sanitize_int($_GET['id'] ?? null);
            if (!$id) api_error('Missing ID');

            $pdo->prepare("DELETE FROM service_directions WHERE id = ?")->execute([$id]);
            audit_log($pdo, 'delete', 'service_direction', $id);
            api_success();
            break;
    }
} catch (Exception $e) {
    api_error('Operation failed');
}
