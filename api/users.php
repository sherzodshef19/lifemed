<?php
require_once '../includes/api_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
switch ($method) {
    case 'GET':
        $stmt = $pdo->query("SELECT id, full_name, username, role, created_at FROM users ORDER BY id ASC");
        api_success($stmt->fetchAll());
        break;

    case 'POST':
        require_admin();
        $data = json_decode(file_get_contents('php://input'), true);
        validate_required($data, ['full_name', 'username', 'role']);

        if (isset($data['id'])) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, role = ? WHERE id = ?");
            $stmt->execute([sanitize_string($data['full_name']), sanitize_string($data['username']), sanitize_string($data['role']), $data['id']]);

            if (!empty($data['password'])) {
                if (strlen($data['password']) < 1 || strlen($data['password']) > 64) {
                    api_error('Пароль должен быть от 1 до 64 символов');
                }
                $hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $data['id']]);
            }
            audit_log($pdo, 'update', 'user', $data['id']);
        } else {
            if (empty($data['password']) || strlen($data['password']) < 1 || strlen($data['password']) > 64) {
                api_error('Пароль должен быть от 1 до 64 символов');
            }
            // Check username uniqueness
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([sanitize_string($data['username'])]);
            if ($check->fetch()) {
                api_error('Username already exists');
            }

            $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([sanitize_string($data['full_name']), sanitize_string($data['username']), $hash, sanitize_string($data['role'])]);
            $newId = $pdo->lastInsertId();
            audit_log($pdo, 'create', 'user', $newId);
        }
        api_success();
        break;

    case 'DELETE':
        require_admin();
        $id = sanitize_int($_GET['id'] ?? null);
        if (!$id) api_error('Missing ID');

        // Prevent deleting yourself
        if ($id == $_SESSION['user_id']) {
            api_error('Cannot delete your own account');
        }

        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        audit_log($pdo, 'delete', 'user', $id);
        api_success();
        break;
}
} catch (PDOException $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) error_log($e->getMessage());
    api_error('Database error', 500);
}
