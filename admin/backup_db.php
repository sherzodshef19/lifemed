<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
require_once '../includes/helpers.php';
check_role(['admin']);

try {
    $tables = [];
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sql = "-- MySQL Backup\n";
    $sql .= "-- LifeMed Clinic: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Create table
        $res = $pdo->query("SHOW CREATE TABLE `$table`");
        $show_create = $res->fetch(PDO::FETCH_ASSOC);
        $sql .= "\n\n" . $show_create['Create Table'] . ";\n\n";

        // Insert data
        $res = $pdo->query("SELECT * FROM `$table`");
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $sql .= "INSERT INTO `$table` VALUES(";
            $values = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = $pdo->quote($val);
                }
            }
            $sql .= implode(",", $values) . ");\n";
        }
        $sql .= "\n\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $filename = "lifemed_backup_" . date("Y-m-d_H-i-s") . ".sql";
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"" . $filename . "\"");
    
    // Log the backup
    audit_log($pdo, 'export_database', 'system', null, ['tables' => count($tables)]);
    
    echo $sql;
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die("Backup failed: " . h($e->getMessage()));
}
