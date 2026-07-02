<?php
require_once '../includes/api_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->query("SELECT id, name FROM service_groups ORDER BY name ASC");
            api_success($stmt->fetchAll());
            break;

        case 'POST':
            require_admin_or_cashier();
            $data = json_decode(file_get_contents('php://input'), true);
            validate_required($data, ['name']);

            if (!empty($data['id'])) {
                $stmt = $pdo->prepare("UPDATE service_groups SET name = ? WHERE id = ?");
                $stmt->execute([sanitize_string($data['name']), $data['id']]);
                audit_log($pdo, 'update', 'service_group', $data['id']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO service_groups (name) VALUES (?)");
                $stmt->execute([sanitize_string($data['name'])]);
                audit_log($pdo, 'create', 'service_group', $pdo->lastInsertId());
            }
            api_success();
            break;

        case 'DELETE':
            require_admin();
            $id = sanitize_int($_GET['id'] ?? null);
            if (!$id) api_error('Missing ID');

            $pdo->prepare("DELETE FROM service_groups WHERE id = ?")->execute([$id]);
            audit_log($pdo, 'delete', 'service_group', $id);
            api_success();
            break;
    }
} catch (Exception $e) {
    api_error('Operation failed');
}
