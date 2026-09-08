<?php

/**
 * CLI tool — Create an admin user.
 *
 * Usage:
 *   php create_admin.php "username" "password" ["Display Name"]
 */

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Db.php';

Env::load(__DIR__ . '/.env');

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'This script must be run from the command line.';
    exit(1);
}

$username    = $argv[1] ?? null;
$password    = $argv[2] ?? null;
$displayName = $argv[3] ?? null;

if ($username === null || $password === null) {
    fwrite(STDERR, "Usage: php create_admin.php \"username\" \"password\" [\"Display Name\"]\n");
    exit(1);
}

$username     = trim($username);
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$db = Db::conn();

try {
    $stmt = $db->prepare(
        'INSERT INTO admin_users (username, password_hash, display_name) VALUES (?, ?, ?)'
    );
    $stmt->bind_param('sss', $username, $passwordHash, $displayName);
    $stmt->execute();
    $id = $db->insert_id;
    $stmt->close();

    echo "✅ Admin created successfully!\n";
    echo "   ID:       {$id}\n";
    echo "   Username: {$username}\n";
    if ($displayName) echo "   Name:     {$displayName}\n";
    echo "\nLog in at /admin/login.php\n";
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        fwrite(STDERR, "❌ Username \"{$username}\" already exists.\n");
        exit(1);
    }
    throw $e;
}
