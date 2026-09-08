<?php

/**
 * CLI helper — register the Telegram webhook together with its secret token.
 *
 * Usage:
 *   php set_webhook.php "https://example.com/telegram_webhook.php"
 *
 * Reads TELEGRAM_BOT_TOKEN and WEBHOOK_SECRET from the environment (.env).
 * The secret is passed as setWebhook's secret_token, after which Telegram
 * sends it in every update's X-Telegram-Bot-Api-Secret-Token header —
 * exactly what WebhookGuard::verify() checks for.
 *
 * Bale equivalent (no secret_token support documented — keep using ?secret=):
 *   curl -X POST "https://tapi.bale.ai/bot<BALE_BOT_TOKEN>/setWebhook" \
 *        -d "url=https://example.com/bale_webhook.php"
 */

require_once __DIR__ . '/Env.php';

Env::load(__DIR__ . '/.env');

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'This script must be run from the command line.';
    exit(1);
}

$url = $argv[1] ?? null;

if ($url === null || !filter_var($url, FILTER_VALIDATE_URL)) {
    fwrite(STDERR, "Usage: php set_webhook.php \"https://example.com/telegram_webhook.php\"\n");
    exit(1);
}

$token  = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$secret = getenv('WEBHOOK_SECRET') ?: '';

if ($token === '' || $token === 'changeme') {
    fwrite(STDERR, "❌ Set TELEGRAM_BOT_TOKEN in .env first.\n");
    exit(1);
}

$params = ['url' => $url];
if ($secret !== '') {
    $params['secret_token'] = $secret;
}

$ch = curl_init("https://api.telegram.org/bot{$token}/setWebhook");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($params),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP {$httpCode}\n";
echo $response === false ? '(empty response)' : $response, "\n";

// ── Register the bot's command menu (shows as the "Menu" button) ──────
$commands = [
    ['command' => 'start',   'description' => 'شروع و اتصال شماره تلفن'],
    ['command' => 'help',    'description' => 'راهنمای ربات'],
    ['command' => 'status',  'description' => 'وضعیت ثبت‌نام و پلتفرم ترجیحی'],
    ['command' => 'contact', 'description' => 'راه‌های ارتباطی با پشتیبانی'],
    ['command' => 'cancel',  'description' => 'بازگشت به صفحه اصلی'],
];

function pushCommands(string $apiBase, string $token, array $commands): void
{
    $ch = curl_init("{$apiBase}/bot{$token}/setMyCommands");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['commands' => $commands], JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "setMyCommands: HTTP {$code} ", ($res === false ? '(no response)' : $res), "\n";
}

echo "--- command menu ---\n";
pushCommands('https://api.telegram.org', $token, $commands);

if (($baleToken = getenv('BALE_BOT_TOKEN')) && $baleToken !== '' && $baleToken !== 'changeme') {
    pushCommands('https://tapi.bale.ai', $baleToken, $commands);
}

// ── Bot profile photo ─────────────────────────────────────────────────
// The bot's avatar (the logo users see in chats) must be set through
// BotFather:
//   1. Open @BotFather on Telegram
//   2. Send /mybots → select your bot → Bot Settings → Bot Picture
//   3. Upload assets/bot-logo.png
//
// Bale: use your bot's dashboard or Bale's BotFather equivalent.

// With a secret registered, verify with:
//   curl -X POST "<your-url>" -H "X-Telegram-Bot-Api-Secret-Token: <WEBHOOK_SECRET>" \
//        -H "Content-Type: application/json" -d '{"update_id":1}'

