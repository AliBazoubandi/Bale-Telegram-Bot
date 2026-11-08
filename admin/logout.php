<?php

require_once __DIR__ . '/session_boot.php';
admin_secure_session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

// Reject GET or invalid CSRF requests without destroying the session
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
} else {
    header('Location: login.php');
}
exit;
