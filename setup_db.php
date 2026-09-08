<?php

/**
 * Database Setup & Migration Script for Windows / Native Environments
 *
 * Runs schema.sql and all migrations automatically.
 * Usage: php setup_db.php
 */

require_once __DIR__ . '/Env.php';

$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    echo "\n[ERROR] .env file not found!\n";
    echo "Please copy .env.example to .env and configure your DB_HOST, DB_USER, DB_PASS, and DB_NAME.\n\n";
    exit(1);
}

Env::load($envPath);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: 'notifier';
$port = (int) (getenv('DB_PORT') ?: 3306);

echo "\n========================================================\n";
echo "   SendMessageBots - Database Initialization & Setup   \n";
echo "========================================================\n\n";

echo "Connecting to MySQL server at {$host}:{$port} as user '{$user}'...\n";

// Connect to MySQL server without database first
$conn = null;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($host, $user, $pass, null, $port);
    $conn->set_charset('utf8mb4');
    echo "[OK] Connected to MySQL server successfully.\n";
} catch (Throwable $e) {
    echo "\n[FAIL] Could not connect to MySQL server:\n";
    echo "       " . $e->getMessage() . "\n\n";
    echo "Tip: Make sure MySQL is running in XAMPP or as a Windows service.\n";
    echo "     Also verify DB_HOST (use 127.0.0.1 on Windows), DB_USER, and DB_PASS in .env\n\n";
    exit(1);
}

// Create database if not exists
echo "Ensuring database '{$name}' exists...\n";
try {
    $conn->query("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($name);
    echo "[OK] Database '{$name}' is selected.\n";
} catch (Throwable $e) {
    echo "[FAIL] Could not create or select database '{$name}': " . $e->getMessage() . "\n";
    exit(1);
}

// Function to execute multi-statement SQL file
function executeSqlFile(mysqli $db, string $filePath, string $description): bool
{
    if (!file_exists($filePath)) {
        echo "[SKIP] {$description} file not found: {$filePath}\n";
        return true;
    }

    echo "Running {$description} (" . basename($filePath) . ")...\n";
    $sql = file_get_contents($filePath);

    try {
        if ($db->multi_query($sql)) {
            do {
                if ($result = $db->store_result()) {
                    $result->free();
                }
            } while ($db->more_results() && $db->next_result());
        }
        echo "[OK] {$description} applied successfully.\n";
        return true;
    } catch (Throwable $e) {
        echo "[WARN] Error during {$description}: " . $e->getMessage() . "\n";
        // Drain any remaining results
        while ($db->more_results() && $db->next_result()) {
            if ($r = $db->store_result()) $r->free();
        }
        return false;
    }
}

// 1. Run main schema
executeSqlFile($conn, __DIR__ . '/schema.sql', 'Initial Schema');

// 2. Run migrations
executeSqlFile($conn, __DIR__ . '/migrate_admin.sql', 'Admin Migration');
executeSqlFile($conn, __DIR__ . '/migrate_attachments.sql', 'Attachments Migration');

// 3. Verify tables
echo "\nVerifying database tables:\n";
$tables = ['apps' => 'sender_apps', 'contacts' => 'contacts', 'channels' => 'contact_channels', 'logs' => 'message_logs', 'admins' => 'admin_users'];
foreach ($tables as $alias => $tableName) {
    $res = $conn->query("SHOW TABLES LIKE '{$tableName}'");
    $exists = ($res && $res->num_rows > 0);
    echo " - Table `{$tableName}`: " . ($exists ? "[EXISTS]" : "[MISSING]") . "\n";
}

// Check admin count
$adminRes = $conn->query("SELECT COUNT(*) as cnt FROM admin_users");
$adminCount = $adminRes ? (int) $adminRes->fetch_assoc()['cnt'] : 0;

echo "\n========================================================\n";
echo "[SUCCESS] Database initialization completed!\n";
if ($adminCount === 0) {
    echo "[NOTE] No admin users found. You can create one by running:\n";
    echo "       php create_admin.php <username> <password> \"Display Name\"\n";
} else {
    echo "[INFO] {$adminCount} admin user(s) configured.\n";
    echo "       Default login: username=admin / password=123456 (if newly created)\n";
}
echo "========================================================\n\n";
