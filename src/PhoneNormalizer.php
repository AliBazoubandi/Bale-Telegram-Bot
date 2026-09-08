<?php

/**
 * Shared phone-number normalization used by the bot conversation flow,
 * NotificationSender::sendToPhone() and every other send path.
 *
 * Rules:
 *  - strip all non-digit characters (this also removes a single leading '+');
 *  - accept the 10–15 digit length window;
 *  - strip exactly one leading '00' international prefix BEFORE the
 *    local-'0' rule, so 00989121234567 -> 989121234567;
 *  - convert one leading trunk '0' to '98' (Iranian local numbers).
 *
 * Examples:
 *   '09121234567'    -> '989121234567'
 *   '+989121234567'  -> '989121234567'
 *   '00989121234567' -> '989121234567'   (previously buggy: 98098...)
 *   '989121234567'   -> '989121234567'   (already normalized: unchanged)
 */
class PhoneNormalizer
{
    public static function normalize(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === null || strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        // Strip ONE leading '00' international prefix before the local-'0'
        // rule ('+' was already removed together with the other non-digits).
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Normalize Iranian-style local numbers (0912...) to international (98912...).
        if (str_starts_with($digits, '0')) {
            $digits = '98' . substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Display counterpart of normalize(): renders a stored international
     * Iranian number ('989121234567') in the familiar local format
     * ('09121234567'). Anything that isn't a 98-prefixed Iranian mobile
     * number is returned unchanged — safe to call on any value.
     */
    public static function toLocalDisplay(string $phone): string
    {
        $digits = ltrim(trim($phone), '+');

        if (preg_match('/^98(9\d{9})$/', $digits)) {
            return '0' . substr($digits, 2);
        }

        return $phone;
    }
}
