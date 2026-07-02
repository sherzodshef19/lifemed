<?php
require_once '../includes/api_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->query("SELECT id, name FROM specialties ORDER BY name ASC");
            api_success($stmt->fetchAll());
            break;

        case 'POST':
            require_admin_or_cashier();
            $data = json_decode(file_get_contents('php://input'), true);
            validate_required($data, ['name']);

            if (isset($data['id'])) {
                $stmt = $pdo->prepare("UPDATE specialties SET name = ? WHERE id = ?");
                $stmt->execute([sanitize_string($data['name']), $data['id']]);
                audit_log($pdo, 'update', 'specialty', $data['id']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO specialties (name) VALUES (?)");
                $stmt->execute([sanitize_string($data['name'])]);
                audit_log($pdo, 'create', 'specialty', $pdo->lastInsertId());
            }
            api_success(['id' => $pdo->lastInsertId()]);
            break;

        case 'DELETE':
            require_admin();
            $id = sanitize_int($_GET['id'] ?? null);
            if (!$id) api_error('Missing ID');

            $pdo->prepare("UPDATE doctors SET specialty_id = NULL WHERE specialty_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM specialties WHERE id = ?")->execute([$id]);
            audit_log($pdo, 'delete', 'specialty', $id);
            api_success();
            break;
    }
} catch (PDOException $e) {
    api_error('Database error', 400);
}
