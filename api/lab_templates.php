<?php
require_once '../includes/api_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->query("SELECT id, title, content, category FROM lab_templates ORDER BY category ASC, title ASC");
            api_success($stmt->fetchAll());
            break;

        case 'POST':
            require_role(['admin', 'doctor']);
            $data = json_decode(file_get_contents('php://input'), true);
            validate_required($data, ['title']);

            if (isset($data['id'])) {
                $stmt = $pdo->prepare("UPDATE lab_templates SET title = ?, content = ?, category = ? WHERE id = ?");
                $stmt->execute([sanitize_string($data['title']), $data['content'] ?? '', sanitize_string($data['category'] ?? 'laboratory'), $data['id']]);
                audit_log($pdo, 'update', 'lab_template', $data['id']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO lab_templates (title, content, category) VALUES (?, ?, ?)");
                $stmt->execute([sanitize_string($data['title']), $data['content'] ?? '', sanitize_string($data['category'] ?? 'laboratory')]);
                audit_log($pdo, 'create', 'lab_template', $pdo->lastInsertId());
            }
            api_success();
            break;

        case 'DELETE':
            require_admin();
            $id = sanitize_int($_GET['id'] ?? null);
            if (!$id) api_error('Missing ID');

            $pdo->prepare("DELETE FROM lab_templates WHERE id = ?")->execute([$id]);
            audit_log($pdo, 'delete', 'lab_template', $id);
            api_success();
            break;
    }
} catch (PDOException $e) {
    api_error('Database error', 400);
}
