<?php

/**
 * Built-in PHP Development/Production Server Router for Windows
 *
 * Emulates Apache .htaccess security rules and handles static assets
 * when running via: php -S 0.0.0.0:8080 router.php
 */

$root = __DIR__;
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = urldecode($uri);

// Normalize directory separators and prevent directory traversal
$path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
if (str_contains($path, '..' . DIRECTORY_SEPARATOR) || str_ends_with($path, '..')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $path);
$cleanPath = ltrim($normalizedPath, '/');

// ── Security Rules (Equivalent to .htaccess) ───────────────────────────

// 1. Block sensitive files & extensions (.env, .sql, .md, .lock, .json, Docker files)
if (
    preg_match('/\.(env(\..*)?|sql|md|lock|json)$/i', $cleanPath) ||
    preg_match('/^(Dockerfile|docker-compose\.yml|\.gitignore)$/i', $cleanPath)
) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// 2. Block internal classes, scripts & non-public directories
if (
    str_starts_with($cleanPath, 'Messenger/') ||
    str_starts_with($cleanPath, 'storage/') ||
    $cleanPath === 'admin/assets/input.css'
) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

// 3. Block direct web access to internal PHP libraries and CLI scripts
$blockedPhpFiles = [
    'Db.php',
    'Env.php',
    'PhoneNormalizer.php',
    'AttachmentValidator.php',
    'WebhookGuard.php',
    'NotificationSender.php',
    'ConversationHandler.php',
    'register_app.php',
    'create_admin.php',
    'example_send.php',
    'set_webhook.php',
    'router.php',
    'check_requirements.php',
];

if (in_array($cleanPath, $blockedPhpFiles, true)) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// ── Resolve Directory Indexing ──────────────────────────────────────────

$fullPath = $root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

if (is_dir($fullPath)) {
    $indexFile = rtrim($fullPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
    if (file_exists($indexFile)) {
        require $indexFile;
        exit;
    }
}

// ── Static Asset Delivery ───────────────────────────────────────────────

if (file_exists($fullPath) && !is_dir($fullPath)) {
    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

    // If it's a PHP file, execute it
    if ($extension === 'php') {
        require $fullPath;
        exit;
    }

    // MIME type mapping for static files
    $mimeTypes = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'json'  => 'application/json; charset=UTF-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'pdf'   => 'application/pdf',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'txt'   => 'text/plain; charset=UTF-8',
    ];

    if (isset($mimeTypes[$extension])) {
        header('Content-Type: ' . $mimeTypes[$extension]);
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    // Default static file handler
    return false;
}

// ── 404 Handler ─────────────────────────────────────────────────────────

http_response_code(404);
echo 'Not Found';
