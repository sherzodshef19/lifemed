<?php
/**
 * API Authentication Middleware
 * Include this at the top of every API endpoint
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated
if (empty($_SESSION['user_id'])) {
    api_error('Unauthorized', 401);
}

/**
 * Check if current user has one of the allowed roles
 */
function require_role($roles = []) {
    if (empty($_SESSION['role'])) {
        api_error('Access denied', 403);
    }
    
    if (!empty($roles) && !in_array($_SESSION['role'], $roles)) {
        api_error('Access denied', 403);
    }
}

/**
 * Check if current user is admin
 */
function require_admin() {
    require_role(['admin']);
}

/**
 * Check if current user is admin or cashier
 */
function require_admin_or_cashier() {
    require_role(['admin', 'cashier']);
}

/**
 * Check if current user is any authenticated role (admin, cashier, doctor)
 */
function require_any_role() {
    require_role(['admin', 'cashier', 'doctor']);
}
