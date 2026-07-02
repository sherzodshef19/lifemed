<?php
// Helper functions for LifeMed CRM

/**
 * JSON response with consistent format
 */
function api_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_success($data = null, $message = '') {
    $response = ['success' => true];
    if ($data !== null) $response['data'] = $data;
    if ($message) $response['message'] = $message;
    api_response($response);
}

function api_error($message, $code = 400) {
    api_response(['success' => false, 'error' => $message], $code);
}

/**
 * Sanitize output - escape HTML entities
 */
function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Validate required fields in request data
 */
function validate_required($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        api_error('Missing required fields: ' . implode(', ', $missing));
    }
}

/**
 * Validate password strength
 * Minimum 6 chars, at least 1 letter and 1 digit
 */
function validate_password($password) {
    if (strlen($password) < 6) {
        return 'Пароль должен быть не менее 6 символов';
    }
    if (!preg_match('/[a-zA-Z]/', $password)) {
        return 'Пароль должен содержать хотя бы одну букву';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Пароль должен содержать хотя бы одну цифру';
    }
    return null; // OK
}

/**
 * Sanitize HTML content — strip dangerous tags and event handler attributes
 */
function sanitize_html($html) {
    // Remove script, style, iframe, object, embed, form tags entirely (with content)
    $html = preg_replace('#<(script|style|iframe|object|embed|form|textarea|input|button)[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|iframe|object|embed|form|textarea|input|button)[^>]*/?>#is', '', $html);

    // Strip all on* event handler attributes
    $html = preg_replace('#\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#is', '', $html);

    // Remove javascript: and data: URLs in href/src attributes
    $html = preg_replace('#((?:href|src|action)\s*=\s*)("javascript:[^"]*"|\'javascript:[^\']*\'|javascript:[^\s>]+)#is', '$1"#', $html);
    $html = preg_replace('#((?:href|src|action)\s*=\s*)("data:[^"]*"|\'data:[^\']*\'|data:[^\s>]+)#is', '$1"#', $html);

    return $html;
}

/**
 * Sanitize string input
 */
function sanitize_string($value) {
    return trim($value ?? '');
}

/**
 * Validate and sanitize integer
 */
function sanitize_int($value) {
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
}

/**
 * Generate CSRF token
 */
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    // Also check JSON body for csrf_token field
    if (empty($token)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['_csrf_token'] ?? '';
    }
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Rate limiting (simple file-based)
 */
function rate_limit($key, $max_attempts = 5, $window = 300) {
    $cache_dir = sys_get_temp_dir() . '/lifemed_rate/';
    if (!is_dir($cache_dir)) mkdir($cache_dir, 0777, true);
    
    $file = $cache_dir . md5($key) . '.json';
    $now = time();
    
    $data = ['attempts' => [], 'blocked_until' => 0];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: $data;
    }
    
    // Check if blocked
    if ($data['blocked_until'] > $now) {
        return false;
    }
    
    // Clean old attempts
    $data['attempts'] = array_filter($data['attempts'], function($t) use ($now, $window) { return $t > $now - $window; });
    
    if (count($data['attempts']) >= $max_attempts) {
        $data['blocked_until'] = $now + $window;
        file_put_contents($file, json_encode($data));
        return false;
    }
    
    $data['attempts'][] = $now;
    file_put_contents($file, json_encode($data));
    return true;
}

/**
 * Audit log
 */
function audit_log($pdo, $action, $entity_type = null, $entity_id = null, $details = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, role, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['role'] ?? 'system',
            $action,
            $entity_type,
            $entity_id,
            is_array($details) ? json_encode($details) : $details,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Silent fail - don't break main flow
    }
}

/**
 * Check if a column exists in a table
 */
function column_exists($pdo, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        $cache[$key] = $stmt->fetch() !== false;
    } catch (PDOException $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

/**
 * Get soft delete WHERE clause for appointments
 */
function appointments_soft_delete_where($pdo) {
    return column_exists($pdo, 'appointments', 'deleted_at') ? " AND a.deleted_at IS NULL" : "";
}

function appointments_soft_delete_where_simple($pdo) {
    return column_exists($pdo, 'appointments', 'deleted_at') ? " AND deleted_at IS NULL" : "";
}

function patients_soft_delete_where($pdo) {
    return column_exists($pdo, 'patients', 'deleted_at') ? " AND deleted_at IS NULL" : "";
}

/**
 * Validate file upload
 */
function validate_upload($file, $allowed_types = [], $max_size = 2097152) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload failed'];
    }
    
    if ($file['size'] > $max_size) {
        return ['valid' => false, 'error' => 'File too large (max ' . round($max_size / 1048576) . 'MB)'];
    }
    
    // Check MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    
    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
    ];
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!empty($allowed_types)) {
        if (!in_array($ext, $allowed_types)) {
            return ['valid' => false, 'error' => 'File type not allowed'];
        }
        $expected_mime = $allowed_mimes[$ext] ?? null;
        if ($expected_mime && $mime !== $expected_mime) {
            return ['valid' => false, 'error' => 'Invalid file content'];
        }
    }
    
    return ['valid' => true, 'ext' => $ext, 'mime' => $mime];
}
