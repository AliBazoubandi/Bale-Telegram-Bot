<?php

/**
 * Auth guard — include at the top of every admin page.
 * Redirects to login.php if not authenticated.
 */

require_once __DIR__ . '/session_boot.php';
admin_secure_session_start();

// CSRF token, generated once per session and embedded in every admin form
// (login stays exempt). Verified in each POST handler via hash_equals().
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../Env.php';
require_once __DIR__ . '/../Db.php';

Env::load(__DIR__ . '/../.env');

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Make admin info available to pages.
$adminId          = $_SESSION['admin_id'];
$adminUsername     = $_SESSION['admin_username']     ?? 'admin';
$adminDisplayName = $_SESSION['admin_display_name'] ?? $adminUsername;
