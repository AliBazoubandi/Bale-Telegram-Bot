<?php

/**
 * Pre-flight System & Environment Diagnostic Tool for Windows
 * Run via: php bin/check_requirements.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

$isCli = (php_sapi_name() === 'cli');
$green = $isCli ? "\033[32m" : '';
$red   = $isCli ? "\033[31m" : '';
$yellow= $isCli ? "\033[33m" : '';
$cyan  = $isCli ? "\033[36m" : '';
$reset = $isCli ? "\033[0m" : '';

function printStatus(string $label, bool $ok, string $details = '', bool $warning = false): void
{
    global $green, $red, $yellow, $reset;
    $statusText = $ok ? "[OK]" : ($warning ? "[WARN]" : "[FAIL]");
    $color = $ok ? $green : ($warning ? $yellow : $red);

    echo sprintf(" %s%-8s%s %-32s %s\n", $color, $statusText, $reset, $label, $details);
}

echo "\n" . $cyan . "========================================================" . $reset . "\n";
echo $cyan . "   SendMessageBots - System & Environment Diagnostics   " . $reset . "\n";
echo $cyan . "========================================================" . $reset . "\n\n";

$allPassed = true;

// 1. PHP Version
$phpVersion = PHP_VERSION;
$versionOk = version_compare($phpVersion, '8.1.0', '>=');
printStatus("PHP Version (>= 8.1)", $versionOk, "Installed: PHP " . $phpVersion);
if (!$versionOk) $allPassed = false;

// 2. Required PHP Extensions
$requiredExtensions = [
    'mysqli'   => 'Database connection to MySQL',
    'curl'     => 'API calls to Telegram and Bale APIs',
    'mbstring' => 'Persian & Unicode string processing',
    'fileinfo' => 'MIME validation for message attachments',
    'openssl'  => 'HTTPS requests & secure token generation',
    'json'     => 'JSON payloads & webhook data processing',
    'session'  => 'Admin panel authentication sessions',
];

echo "\n" . $cyan . "── Checking PHP Extensions ──" . $reset . "\n";
foreach ($requiredExtensions as $ext => $purpose) {
    $loaded = extension_loaded($ext);
    printStatus("Extension: {$ext}", $loaded, $purpose);
    if (!$loaded) $allPassed = false;
}

// 3. php.ini File Upload Limits
echo "\n" . $cyan . "── Checking Upload Configurations ──" . $reset . "\n";
$maxUpload = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');
printStatus("upload_max_filesize", true, "Value: {$maxUpload} (Recommended >= 10M)");
printStatus("post_max_size", true, "Value: {$postMax} (Recommended >= 10M)");

// 4. .env File Check
echo "\n" . $cyan . "── Checking Configuration (.env) ──" . $reset . "\n";
$envPath = ROOT_PATH . '/.env';
$envExists = file_exists($envPath);
printStatus(".env File", $envExists, $envExists ? "Found at {$envPath}" : "Missing! Copy .env.example to .env");
if (!$envExists) {
    $allPassed = false;
} else {
    $botLogo = getenv('BOT_LOGO_PATH') ?: 'assets/bot-logo.png';
    $botLogoFull = ROOT_PATH . '/' . $botLogo;
    printStatus("Bot Logo Asset", file_exists($botLogoFull), $botLogo);

    $webhookSecret = getenv('WEBHOOK_SECRET');
    $secretOk = !empty($webhookSecret) && $webhookSecret !== 'changeme';
    printStatus("WEBHOOK_SECRET Configured", $secretOk, $secretOk ? "Set" : "Warning: Still default or empty", true);

    $tgToken = getenv('TELEGRAM_BOT_TOKEN');
    $baleToken = getenv('BALE_BOT_TOKEN');
    printStatus("TELEGRAM_BOT_TOKEN", !empty($tgToken) && $tgToken !== 'changeme', $tgToken ? "Configured" : "Not set", true);
    printStatus("BALE_BOT_TOKEN", !empty($baleToken) && $baleToken !== 'changeme', $baleToken ? "Configured" : "Not set", true);
}

// 5. Storage Directory Permissions
echo "\n" . $cyan . "── Checking Storage & Permissions ──" . $reset . "\n";
$storageDir = AttachmentValidator::storageDir();
$storageOk = is_dir($storageDir) && is_writable($storageDir);
printStatus("Storage Directory", $storageOk, "Path: {$storageDir} (" . ($storageOk ? "Writable" : "Not writable") . ")");
if (!$storageOk) $allPassed = false;

// 6. MySQL Database Connection & Tables
echo "\n" . $cyan . "── Checking Database Connection ──" . $reset . "\n";
if ($envExists) {
    try {
        $db = Db::conn();
        printStatus("MySQL Connection", true, "Connected to " . (getenv('DB_HOST') ?: '127.0.0.1') . ":" . (getenv('DB_PORT') ?: 3306));

        // Actual required database table names
        $tables = [
            'contacts'         => 'Contacts table',
            'contact_channels' => 'Channel links table',
            'bot_sessions'     => 'Conversation state machine',
            'sender_apps'      => 'Sender applications',
            'message_logs'     => 'Message logs & attachments',
            'admin_users'      => 'Admin user credentials',
        ];
        $missingTables = [];

        foreach (array_keys($tables) as $table) {
            $res = $db->query("SHOW TABLES LIKE '{$table}'");
            if ($res->num_rows === 0) {
                $missingTables[] = $table;
            }
        }

        if (empty($missingTables)) {
            printStatus("Database Tables", true, "All 6 required tables exist");
        } else {
            printStatus("Database Tables", false, "Missing tables: " . implode(', ', $missingTables) . " (Run scripts/setup_database.bat)");
            $allPassed = false;
        }

    } catch (Throwable $e) {
        printStatus("MySQL Connection", false, "Error: " . $e->getMessage());
        $allPassed = false;
    }
} else {
    printStatus("MySQL Connection", false, "Skipped because .env file is missing");
}

echo "\n" . $cyan . "========================================================" . $reset . "\n";
if ($allPassed) {
    echo $green . " [SUCCESS] System is fully configured and ready to run!" . $reset . "\n";
} else {
    echo $yellow . " [ATTENTION] Some requirements or configurations need attention." . $reset . "\n";
}
echo $cyan . "========================================================" . $reset . "\n\n";

exit($allPassed ? 0 : 1);
