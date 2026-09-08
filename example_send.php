<?php

/**
 * Example: send notifications (text, photos, or PDF documents) from PHP / CLI.
 *
 * Before running, register an app first:
 *   php register_app.php "Test App"
 *
 * Then run:
 *   php example_send.php
 */

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/NotificationSender.php';

Env::load(__DIR__ . '/.env');

// ── Fill these in ───────────────────────────────────────────────────
$appId = 1;                          // sender_apps.id of your registered app
$phone = '98XXXXXXXXXX';             // normalized phone number

$sender = new NotificationSender();

// Example 1: Send text notification with optional link
// $results = $sender->sendToPhone(
//     $phone,
//     $appId,
//     'سلام! فاکتور شماره ۱۲۳۴ آماده بررسی است.',   // message
//     'https://example.com/invoices/1234'              // optional link
// );

// Example 2: Send message with a Photo attachment (direct local file path or public URL)
// $results = $sender->sendToPhone(
//     $phone,
//     $appId,
//     'تصویر فاکتور ضمیمه گردید.',
//     'https://example.com',
//     __DIR__ . '/admin/assets/images/telegram.webp', // local file path or URL
//     'photo'                                         // attachment type: 'photo' or 'document'
// );

// Example 3: Send message with a PDF attachment (direct local file path or public URL)
$results = $sender->sendToPhone(
    $phone,
    $appId,
    'فایل PDF فاکتور ضمیمه گردید.',
    'https://example.com',
    __DIR__ . '/uploads/attachments/sample.pdf', // or URL
    'document'
);

foreach ($results as $r) {
    $line = "platform=" . ($r['platform'] ?? 'none') . " status={$r['status']}";
    if (isset($r['attachment'])) {
        $line .= " attachment=" . ($r['attachment'] ?: 'none');
    }
    if (isset($r['preferred'])) {
        $line .= " preferred=" . ($r['preferred'] ? 'yes' : 'no');
    }
    if (isset($r['error'])) {
        $line .= " error={$r['error']}";
    }
    echo $line . PHP_EOL;
}
