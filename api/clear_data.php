<?php
require_once '../includes/api_auth.php';
require_role(['admin']);

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') api_error('Method not allowed', 405);

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? '';
$confirm = $data['confirm'] ?? '';

if ($confirm !== 'DELETE') {
    api_error('Подтверждение не отправлено');
}

try {
    $message = '';

    switch ($type) {
        case 'patients':
            $before = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("DELETE FROM lab_results");
            $pdo->exec("DELETE FROM appointments");
            $pdo->exec("DELETE FROM patients");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $message = "Удалено пациентов: $before";
            break;

        case 'doctors':
            $before = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("DELETE FROM doctor_services");
            $tables = $pdo->query("SHOW TABLES LIKE 'working_hours'")->fetch();
            if ($tables) {
                $pdo->exec("DELETE FROM working_hours");
            }
            $pdo->exec("UPDATE appointments SET doctor_id = NULL");
            $pdo->exec("DELETE FROM doctors");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $message = "Удалено врачей: $before";
            break;

        case 'lab_results':
            $before = $pdo->query("SELECT COUNT(*) FROM lab_results")->fetchColumn();
            $pdo->exec("DELETE FROM lab_results");
            $message = "Удалено результатов анализов: $before";
            break;

        case 'lab_templates':
            $before = $pdo->query("SELECT COUNT(*) FROM lab_templates")->fetchColumn();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("DELETE FROM lab_results");
            $pdo->exec("DELETE FROM lab_templates");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $message = "Удалено шаблонов и результатов: $before";
            break;

        case 'services':
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("DELETE FROM doctor_services");
            $pdo->exec("DELETE FROM appointments");
            $pdo->exec("DELETE FROM services");
            $pdo->exec("DELETE FROM service_directions");
            $pdo->exec("DELETE FROM service_groups");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $message = 'Все услуги, направления и группы удалены';
            break;

        case 'appointments':
            $before = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
            $pdo->exec("DELETE FROM appointments");
            $message = "Удалено приёмов: $before";
            break;

        case 'audit_log':
            $before = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
            $pdo->exec("DELETE FROM audit_log");
            $message = "Удалено записей аудита: $before";
            break;

        default:
            api_error('Неизвестный тип очистки');
    }

    audit_log($pdo, 'clear', $type, null, ['message' => $message]);
    api_success(['message' => $message]);

} catch (PDOException $e) {
    error_log('clear_data error: ' . $e->getMessage());
    api_error('Ошибка базы данных');
}
