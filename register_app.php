<?php

/**
 * CLI tool — Register a new sender app.
 *
 * Usage:
 *   php register_app.php "My App Name"
 *
 * Outputs the generated app_key that the app must use for API calls.
 */

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Db.php';

Env::load(__DIR__ . '/.env');

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'This script must be run from the command line.';
    exit(1);
}

$appName = $argv[1] ?? null;

if ($appName === null || trim($appName) === '') {
    fwrite(STDERR, "Usage: php register_app.php \"App Name\"\n");
    exit(1);
}

$appName = trim($appName);
$appKey  = bin2hex(random_bytes(24));  // 48-char hex key

$db = Db::conn();

try {
    $stmt = $db->prepare(
        'INSERT INTO sender_apps (app_name, app_key) VALUES (?, ?)'
    );
    $stmt->bind_param('ss', $appName, $appKey);
    $stmt->execute();
    $id = $db->insert_id;
    $stmt->close();

    echo "✅ App registered successfully!\n";
    echo "   ID:       {$id}\n";
    echo "   Name:     {$appName}\n";
    echo "   API Key:  {$appKey}\n";
    echo "\nUse this key in the X-API-Key header when calling api_send.php.\n";
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        fwrite(STDERR, "❌ An app named \"{$appName}\" already exists.\n");
        exit(1);
    }
    throw $e;
}
