<?php

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/WebhookGuard.php';
require_once __DIR__ . '/ConversationHandler.php';
require_once __DIR__ . '/Messenger/MessengerFactory.php';

Env::load(__DIR__ . '/.env');

// Reject any update that does not carry our secret — via the
// X-Telegram-Bot-Api-Secret-Token header (or ?secret= for Bale).
// On mismatch: 403 and total silence (nothing is logged).
WebhookGuard::verify();

// Telegram sends the update as raw JSON in the POST body.
$update = json_decode(file_get_contents('php://input'), true);

$message  = $update['message'] ?? null;
$chat     = $message['chat'] ?? [];
$chatId   = $chat['id'] ?? null;
$text     = $message['text'] ?? '';
$chatType = $chat['type'] ?? null;

if ($chatId === null) {
    // Not a plain text/contact message (sticker, edited message, etc.) — ignore.
    http_response_code(200);
    exit;
}

// Determine if this update originated from a group, supergroup, or channel
$isGroup = ($chatType !== null && $chatType !== 'private')
    || isset($chat['title'])
    || (is_numeric($chatId) && (int) $chatId < 0);

if ($isGroup) {
    // In groups, ignore all normal chatter so the bot never spams the group.
    // Helper: allow admins to get the Chat ID or remove lingering keyboards via /id or /clean
    $trimmedText = trim($text);
    if (preg_match('/^\/(id|chatid|groupid|clean|remove_keyboard)(@\w+)?$/i', $trimmedText)) {
        try {
            $groupTitle = $chat['title'] ?? 'این گفتگو';
            $bot = MessengerFactory::make('telegram');
            $bot->sendMessage(
                (string) $chatId,
                "📌 شناسه چت این گروه: `{$chatId}`\n"
                . "🏷 نام گروه: {$groupTitle}\n\n"
                . "✅ کیبورد ربات از گروه پاک شد.",
                [
                    'reply_markup' => ['remove_keyboard' => true],
                ]
            );
        } catch (Throwable $e) {
            error_log('[telegram_webhook group id] ' . $e->getMessage());
        }
    }

    // Never pass group messages to ConversationHandler
    http_response_code(200);
    exit;
}

// Extract user profile from the sender.
$from      = $message['from'] ?? [];
$username  = $from['username']   ?? null;
$firstName = $from['first_name'] ?? null;
$lastName  = $from['last_name']  ?? null;

// If the user tapped the "Share phone number" button, Telegram sends a
// 'contact' object instead of text. Only trust it if it's the sender's
// own contact card (Telegram allows forwarding someone else's contact too).
$contactPhone = null;
if (isset($message['contact']['phone_number'])) {
    $contactUserId = $message['contact']['user_id'] ?? null;
    $fromUserId    = $message['from']['id'] ?? null;

    if ($contactUserId !== null && $contactUserId === $fromUserId) {
        $contactPhone = $message['contact']['phone_number'];
    }
}

try {
    (new ConversationHandler('telegram'))->handle(
        (string) $chatId,
        $text,
        $contactPhone,
        $username,
        $firstName,
        $lastName,
        $chatType
    );
} catch (Throwable $e) {
    error_log('[telegram_webhook] ' . $e->getMessage());
}

http_response_code(200);

