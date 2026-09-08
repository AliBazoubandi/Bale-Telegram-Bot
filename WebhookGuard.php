<?php

/**
 * Shared authentication guard for incoming bot webhooks — required by BOTH
 * telegram_webhook.php and bale_webhook.php.
 *
 * Telegram sends the secret in the X-Telegram-Bot-Api-Secret-Token header.
 * Bale does not document that header, so a ?secret=<token> query parameter
 * is accepted as well. Both are compared against WEBHOOK_SECRET from the
 * environment using hash_equals(). On mismatch the request dies with 403
 * and nothing is written to any log.
 */
class WebhookGuard
{
    public static function verify(): void
    {
        $secret = getenv('WEBHOOK_SECRET') ?: '';

        $provided = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']
                 ?? $_GET['secret']
                 ?? '';

        if ($secret === ''
            || !is_string($provided)
            || $provided === ''
            || !hash_equals($secret, $provided)
        ) {
            http_response_code(403);
            exit;
        }
    }
}
