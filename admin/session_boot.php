<?php

/**
 * Hardened session bootstrap for the admin panel.
 *
 * Include this file and call admin_secure_session_start() INSTEAD of a bare
 * session_start() in every entry point that touches $_SESSION.
 */

function admin_secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // The 'secure' cookie flag is enabled only when the request actually
    // arrived over HTTPS (directly, or via a TLS-terminating proxy that sets
    // X-Forwarded-Proto), so plain-HTTP local development keeps working.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $isHttps,
    ]);

    session_start();
}
