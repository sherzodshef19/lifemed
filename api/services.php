<?php
require_once '../includes/api_auth.php';
require_once '../includes/helpers.php';

$has_commission = column_exists($pdo, 'services', 'commission_pct');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $comm_col = $has_commission ? ', s.commission_pct' : '';
            $sql = "SELECT s.id, s.name, s.price, s.direction_id, sd.name as direction_name, sd.group_id, sg.name as group_name{$comm_col}
                    FROM services s
                    LEFT JOIN service_directions sd ON s.direction_id = sd.id
                    LEFT JOIN service_groups sg ON sd.group_id = sg.id
                    ORDER BY sg.name ASC, sd.name ASC, s.name ASC";
            $stmt = $pdo->query($sql);
            api_success($stmt->fetchAll());
            break;

        case 'POST':
            require_admin_or_cashier();
            $data = json_decode(file_get_contents('php://input'), true);
            validate_required($data, ['name']);

            $direction_id = !empty($data['direction_id']) ? (int)$data['direction_id'] : null;
            $commission_pct = ($has_commission && isset($data['commission_pct'])) ? (float)$data['commission_pct'] : 0;

            if (!empty($data['id'])) {
                if ($has_commission) {
                    $stmt = $pdo->prepare("UPDATE services SET name = ?, price = ?, commission_pct = ?, direction_id = ? WHERE id = ?");
                    $stmt->execute([sanitize_string($data['name']), (float)$data['price'], $commission_pct, $direction_id, $data['id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE services SET name = ?, price = ?, direction_id = ? WHERE id = ?");
                    $stmt->execute([sanitize_string($data['name']), (float)$data['price'], $direction_id, $data['id']]);
                }
                audit_log($pdo, 'update', 'service', $data['id']);
            } else {
                if ($has_commission) {
                    $stmt = $pdo->prepare("INSERT INTO services (name, price, commission_pct, direction_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([sanitize_string($data['name']), (float)$data['price'], $commission_pct, $direction_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO services (name, price, direction_id) VALUES (?, ?, ?)");
                    $stmt->execute([sanitize_string($data['name']), (float)$data['price'], $direction_id]);
                }
                audit_log($pdo, 'create', 'service', $pdo->lastInsertId());
            }
            api_success();
            break;

        case 'DELETE':
            require_admin();
            $id = sanitize_int($_GET['id'] ?? null);
            if (!$id) api_error('Missing ID');

            $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
            audit_log($pdo, 'delete', 'service', $id);
            api_success();
            break;
    }
} catch (Exception $e) {
    api_error('Operation failed');
}
