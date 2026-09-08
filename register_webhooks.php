<?php

/**
 * Unified Webhook Registration for Telegram and Bale
 *
 * Usage:
 *   php register_webhooks.php "https://example.com"
 */

require_once __DIR__ . '/Env.php';
Env::load(__DIR__ . '/.env');

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit(1);
}

$inputUrl = $argv[1] ?? '';
if (empty($inputUrl) || !filter_var($inputUrl, FILTER_VALIDATE_URL)) {
    echo "\n[ERROR] Invalid URL provided.\n";
    echo "Usage: php register_webhooks.php \"https://your-public-url.com\"\n\n";
    exit(1);
}

// Clean base URL (remove trailing slash and file names if passed)
$baseUrl = rtrim($inputUrl, '/');
$baseUrl = preg_replace('/\/(telegram_webhook|bale_webhook)\.php.*$/i', '', $baseUrl);

$telegramToken = getenv('TELEGRAM_BOT_TOKEN');
$baleToken     = getenv('BALE_BOT_TOKEN');
$webhookSecret = getenv('WEBHOOK_SECRET') ?: '';

echo "\n========================================================\n";
echo "   SendMessageBots - Webhook Registration Tool\n";
echo "========================================================\n";
echo "Public Base URL: {$baseUrl}\n";
echo "Webhook Secret:  " . (!empty($webhookSecret) ? '[Configured]' : '[Not Set]') . "\n";
echo "========================================================\n\n";

// ── 1. Telegram Webhook ───────────────────────────────────────────────
echo "1. Registering Telegram Webhook...\n";
if (empty($telegramToken) || $telegramToken === 'changeme') {
    echo "   [SKIP] TELEGRAM_BOT_TOKEN is not set in .env\n";
} else {
    $tgWebhookUrl = $baseUrl . '/telegram_webhook.php';
    echo "   Target URL: {$tgWebhookUrl}\n";

    $params = ['url' => $tgWebhookUrl];
    if (!empty($webhookSecret) && $webhookSecret !== 'changeme') {
        $params['secret_token'] = $webhookSecret;
    }

    $ch = curl_init("https://api.telegram.org/bot{$telegramToken}/setWebhook");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "   HTTP Response [{$code}]: " . ($res ?: '(no response)') . "\n";
}

// ── 2. Bale Webhook ───────────────────────────────────────────────────
echo "\n2. Registering Bale Webhook...\n";
if (empty($baleToken) || $baleToken === 'changeme') {
    echo "   [SKIP] BALE_BOT_TOKEN is not set in .env\n";
} else {
    // Bale receives secret via query param ?secret=...
    $baleWebhookUrl = $baseUrl . '/bale_webhook.php';
    if (!empty($webhookSecret) && $webhookSecret !== 'changeme') {
        $baleWebhookUrl .= '?secret=' . urlencode($webhookSecret);
    }
    echo "   Target URL: {$baleWebhookUrl}\n";

    $ch = curl_init("https://tapi.bale.ai/bot{$baleToken}/setWebhook");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['url' => $baleWebhookUrl]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "   HTTP Response [{$code}]: " . ($res ?: '(no response)') . "\n";
}

// ── 3. Register Command Menu ──────────────────────────────────────────
echo "\n3. Registering Bot Commands Menu...\n";
$commands = [
    ['command' => 'start',   'description' => 'شروع و اتصال شماره تلفن'],
    ['command' => 'help',    'description' => 'راهنمای ربات'],
    ['command' => 'status',  'description' => 'وضعیت ثبت‌نام و پلتفرم ترجیحی'],
    ['command' => 'contact', 'description' => 'راه‌های ارتباطی با پشتیبانی'],
    ['command' => 'cancel',  'description' => 'بازگشت به صفحه اصلی'],
];

function setMenuCommands(string $apiBase, string $token, array $commands, string $botName): void
{
    $ch = curl_init("{$apiBase}/bot{$token}/setMyCommands");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['commands' => $commands], JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "   [{$botName}] HTTP {$code}: " . ($res ?: '(no response)') . "\n";
}

if (!empty($telegramToken) && $telegramToken !== 'changeme') {
    setMenuCommands('https://api.telegram.org', $telegramToken, $commands, 'Telegram');
}
if (!empty($baleToken) && $baleToken !== 'changeme') {
    setMenuCommands('https://tapi.bale.ai', $baleToken, $commands, 'Bale');
}

echo "\n========================================================\n";
echo "   Webhook Registration Finished!\n";
echo "========================================================\n\n";
