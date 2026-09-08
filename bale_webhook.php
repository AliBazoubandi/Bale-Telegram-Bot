<?php

require_once __DIR__ . '/src/bootstrap.php';

// Bale doesn't document the secret-token header, so the guard also accepts
// ?secret=<token>. On mismatch: 403 and total silence (nothing is logged).
WebhookGuard::verify();

// Bale's Bot API mirrors Telegram's Update shape, including 'contact'
// messages from a request_contact button. Verify against real traffic
// once you have it — adjust the extraction below if the shape differs.
$update = json_decode(file_get_contents('php://input'), true);

$message  = $update['message'] ?? null;
$chat     = $message['chat'] ?? [];
$chatId   = $chat['id'] ?? null;
$text     = $message['text'] ?? '';
$chatType = $chat['type'] ?? null;

if ($chatId === null) {
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
            $bot = MessengerFactory::make('bale');
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
            error_log('[bale_webhook group id] ' . $e->getMessage());
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

$contactPhone = null;
if (isset($message['contact']['phone_number'])) {
    $contactUserId = $message['contact']['user_id'] ?? null;
    $fromUserId    = $message['from']['id'] ?? null;

    // Only trust the contact if it's the sender's own number.
    // If Bale's payload doesn't include user_id on the contact object,
    // this check will always fail closed — fall back to text entry, or
    // relax this once you've confirmed the real payload shape.
    if ($contactUserId !== null && $contactUserId === $fromUserId) {
        $contactPhone = $message['contact']['phone_number'];
    }
}

try {
    (new ConversationHandler('bale'))->handle(
        (string) $chatId,
        $text,
        $contactPhone,
        $username,
        $firstName,
        $lastName,
        $chatType
    );
} catch (Throwable $e) {
    error_log('[bale_webhook] ' . $e->getMessage());
}

http_response_code(200);

