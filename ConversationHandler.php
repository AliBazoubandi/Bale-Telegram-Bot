<?php

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/PhoneNormalizer.php';
require_once __DIR__ . '/Jalali.php';
require_once __DIR__ . '/Messenger/MessengerFactory.php';

/**
 * Handles the onboarding flow for a single incoming update from either
 * Bale or Telegram. Both webhooks normalize their payload and hand it
 * to handle().
 */
class ConversationHandler
{
    private MessengerClientInterface $bot;
    private string $platform;

    /** Human-readable platform labels (Persian). */
    private const PLATFORM_LABELS = [
        'telegram' => 'تلگرام',
        'bale'     => 'بله',
    ];


    /** Help text for /help and unknown commands. */
    private const HELP_TEXT =
        "راهنمای ربات سامانه ارسال پیام هلدینگ رهباریان\n\n"
        . "🟢 شروع — شروع مجدد و نمایش پیام خوش‌آمدگویی\n"
        . "📖 راهنما — نمایش همین راهنما\n"
        . "📋 وضعیت — وضعیت ثبت‌نام و پلتفرم ترجیحی\n"
        . "📞 پشتیبانی — راه‌های ارتباطی با پشتیبانی\n"
        . "❌ لغو عملیات — بازگشت به صفحه اصلی\n\n"
        . "برای دریافت اعلان‌ها کافی است یک‌بار شماره تلفن خود را با دکمه «اشتراک گذاری شماره تلفن» ثبت کنید.";

    /** Text shown when user runs /status command. */
    private const STATUS_TEXT_TEMPLATE =
        "📋 وضعیت ثبت‌نام شما در سامانه:\n\n"
        . "📞 شماره تلفن: {phone}\n"
        . "🏷 پلتفرم: {platform}\n"
        . "⭐ پلتفرم ترجیحی: {preferred}\n"
        . "🕐 تاریخ ثبت‌نام: {created_at}\n";

    /** Text shown when user runs /contact command. */
    private const CONTACT_TEXT =
        "📞 راه‌های ارتباطی با تیم پشتیبانی:\n\n"
        . "✉️ ایمیل: support@rahbarian-rabin.com\n"
        . "🌐 وب‌سایت: https://rahbarian-rabin.com\n"
        . "💬 تلگرام پشتیبانی: @rahbarian_rabin_support\n\n"
        . "تیم هوش تجاری رابین سامانه در خدمت شماست.";

    public function __construct(string $platform)
    {
        $this->platform = $platform;
        $this->bot = MessengerFactory::make($platform);
    }

    /**
     * @param string      $chatId
     * @param string      $text          Plain text of the message, '' if none.
     * @param string|null $contactPhone  Phone number, if the user tapped the
     *                                   "Share phone number" button.
     * @param string|null $username      @username from the platform (without @).
     * @param string|null $firstName     User's first name from the platform.
     * @param string|null $lastName      User's last name from the platform.
     * @param string|null $chatType      Chat type ('private', 'group', etc.)
     */
    public function handle(
        string  $chatId,
        string  $text,
        ?string $contactPhone = null,
        ?string $username     = null,
        ?string $firstName    = null,
        ?string $lastName     = null,
        ?string $chatType     = null
    ): void {
        // Group and channel chats must never be handled by the onboarding conversation handler.
        if (($chatType !== null && $chatType !== 'private') || (is_numeric($chatId) && (int) $chatId < 0)) {
            return;
        }

        $text = trim($text);
        $state = $this->getState($chatId);

        // Explicit commands (/start, /help, ...) are handled first, in any state.
        if ($this->handleCommand($chatId, $text)) {
            return;
        }

        if ($state === 'new') {
            $this->sendWelcome($chatId);
            return;
        }

        if ($state === 'awaiting_phone') {
            $phone = $contactPhone !== null
                ? PhoneNormalizer::normalize($contactPhone)
                : PhoneNormalizer::normalize($text);

            if ($phone === null) {
                $this->bot->sendMessage(
                    $chatId,
                    "این شبیه یک شماره تلفن معتبر نیست. لطفاً دوباره تلاش کنید، "
                    . "یا از دکمه اشتراک‌گذاری زیر استفاده کنید.",
                    ['reply_markup' => $this->contactRequestKeyboard()]
                );
                return;
            }

            // Build display name from first + last name.
            $displayName = trim(($firstName ?? '') . ' ' . ($lastName ?? '')) ?: null;

            $this->linkContact($phone, $chatId, $username, $displayName);

            // Check whether this contact already has the OTHER platform linked.
            $otherPlatform = $this->getOtherLinkedPlatform($phone);

            if ($otherPlatform !== null) {
                // User is on both platforms — ask which one they prefer.
                $this->setState($chatId, 'awaiting_preference');
                $currentLabel = self::PLATFORM_LABELS[$this->platform] ?? $this->platform;
                $otherLabel   = self::PLATFORM_LABELS[$otherPlatform]  ?? $otherPlatform;

                $this->bot->sendMessage(
                    $chatId,
                    "شماره شما متصل شد ✅\n\n"
                    . "متوجه شدیم که شما قبلاً در *{$otherLabel}* هم ثبت‌نام کرده‌اید. "
                    . "کدام پلتفرم را برای دریافت اعلان‌ها ترجیح می‌دهید؟ "
                    . "اعلان‌ها ابتدا به پلتفرم ترجیحی شما ارسال خواهند شد.\n\n"
                    . "یکی را انتخاب کنید:",
                    [
                        'parse_mode'   => 'Markdown',
                        'reply_markup' => $this->preferenceKeyboard($this->platform, $otherPlatform),
                    ]
                );
                return;
            }

            // Only one platform — no preference question needed.
            $this->setState($chatId, 'linked');
            $this->bot->sendMessage(
                $chatId,
                "ممنون! شماره شما متصل شد. از این به بعد اعلان‌ها را اینجا دریافت خواهید کرد.",
                ['reply_markup' => $this->menuKeyboard()]
            );
            return;
        }

        if ($state === 'awaiting_preference') {
            $chosen = $this->parsePreferenceChoice($text);

            if ($chosen === null) {
                $this->bot->sendMessage(
                    $chatId,
                    "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:",
                    ['reply_markup' => $this->preferenceKeyboard('telegram', 'bale')]
                );
                return;
            }

            // Save the preference.
            $phone = $this->getPhoneByChatId($chatId);
            if ($phone !== null) {
                $this->setPreferredPlatform($phone, $chosen);
            }

            $this->setState($chatId, 'linked');
            $chosenLabel = self::PLATFORM_LABELS[$chosen] ?? $chosen;
            $this->bot->sendMessage(
                $chatId,
                "عالی! *{$chosenLabel}* به عنوان پلتفرم ترجیحی شما ثبت شد ✅\n"
                . "اعلان‌ها ابتدا در {$chosenLabel} برای شما ارسال خواهند شد.",
                [
                    'parse_mode'   => 'Markdown',
                    'reply_markup' => $this->menuKeyboard(),
                ]
            );
            return;
        }

        // Already linked, and sent something else — nothing to do for now.
        $this->bot->sendMessage($chatId, "شما از قبل متصل و راه‌اندازی شده‌اید", [
            'reply_markup' => $this->menuKeyboard(),
        ]);
    }

    // ------------------------------------------------------------------
    //  Commands & welcome page
    // ------------------------------------------------------------------

    /**
     * Handles explicit bot commands in any state. Returns true when the
     * text was a command and the update is fully handled.
     */
    private function handleCommand(string $chatId, string $text): bool
    {
        if ($text === '/start' || $text === '/menu' || $text === '/cancel'
            || $text === '🟢 شروع' || $text === '❌ لغو عملیات') {
            $this->sendWelcome($chatId);
            return true;
        }

        if ($text === '/help' || $text === '📖 راهنما') {
            $this->bot->sendMessage($chatId, self::HELP_TEXT, [
                'reply_markup' => $this->menuKeyboard(),
            ]);
            return true;
        }

        if ($text === '/status' || $text === '📋 وضعیت') {
            $this->sendStatus($chatId);
            return true;
        }

        if ($text === '/contact' || $text === '📞 پشتیبانی') {
            $this->bot->sendMessage($chatId, self::CONTACT_TEXT, [
                'reply_markup' => $this->menuKeyboard(),
            ]);
            return true;
        }

        // Unknown /command — nudge the user toward the help instead of
        // letting it fall into the phone-number flow.
        if (str_starts_with($text, '/') && strlen($text) > 1) {
            $this->bot->sendMessage(
                $chatId,
                'این دستور را نمی‌شناسم. 🤔' . "\n" . self::HELP_TEXT,
                ['reply_markup' => $this->menuKeyboard()]
            );
            return true;
        }

        return false;
    }

    /**
     * The bot's landing page. If the user is already linked on this
     * platform, show a welcome-back message with the menu. Otherwise,
     * start the phone-sharing onboarding flow.
     */
    private function sendWelcome(string $chatId): void
    {
        // If the user is already registered on this platform, don't
        // let them go through the phone flow again.
        $existingPhone = $this->getPhoneByChatId($chatId);

        if ($existingPhone !== null) {
            $this->setState($chatId, 'linked');
            $this->bot->sendMessage(
                $chatId,
                '✅ شما قبلاً با شماره '
                . PhoneNormalizer::toLocalDisplay($existingPhone)
                . " ثبت‌نام کرده‌اید.\n"
                . 'از منوی زیر می‌توانید گزینه مورد نظرتان را انتخاب کنید.',
                ['reply_markup' => $this->menuKeyboard()]
            );
            return;
        }

        $this->setState($chatId, 'awaiting_phone');

        $welcomeMessage = 'برای فعال‌سازی، روی دکمه زیر بزنید تا شماره تلفنتان را به اشتراک بگذارید، یا فقط آن را وارد کنید (مثلاً 09121234567).';

        $this->bot->sendMessage($chatId, $welcomeMessage, [
            'reply_markup' => $this->contactRequestKeyboard(),
        ]);
    }

    /**
     * Sends the user's registration status when they run /status.
     */
    private function sendStatus(string $chatId): void
    {
        $phone = $this->getPhoneByChatId($chatId);

        if ($phone === null) {
            $this->bot->sendMessage(
                $chatId,
                'شما هنوز در سامانه ثبت‌نام نکرده‌اید. برای شروع از دستور /start استفاده کنید.'
            );
            return;
        }

        $db = Db::conn();

        // Get contact info including preferred platform and created_at
        $stmt = $db->prepare(
            'SELECT c.phone_number, c.preferred_platform, c.created_at
             FROM contacts c
             JOIN contact_channels cc ON cc.contact_id = c.id
             WHERE c.phone_number = ? AND cc.platform = ?
             LIMIT 1'
        );
        $stmt->bind_param('ss', $phone, $this->platform);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            $this->bot->sendMessage($chatId, 'خطا در دریافت اطلاعات. لطفاً مجدداً تلاش کنید.');
            return;
        }

        $platformLabel = self::PLATFORM_LABELS[$this->platform] ?? $this->platform;
        $preferred = $result['preferred_platform']
            ? (self::PLATFORM_LABELS[$result['preferred_platform']] ?? $result['preferred_platform'])
            : 'تنظیم نشده';

        $text = strtr(self::STATUS_TEXT_TEMPLATE, [
            '{phone}'      => PhoneNormalizer::toLocalDisplay($result['phone_number']),
            '{platform}'   => $platformLabel,
            '{preferred}'  => $preferred,
            '{created_at}' => Jalali::formatDate($result['created_at']),
        ]);

        $this->bot->sendMessage($chatId, $text);
    }

    // ------------------------------------------------------------------
    //  Keyboards & Menus
    // ------------------------------------------------------------------

    /**
     * A reply keyboard with the phone-sharing button on top and the
     * persistent command menu buttons below.
     */
    private function contactRequestKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => '📱 اشتراک گذاری شماره تلفن', 'request_contact' => true]],
                [
                    ['text' => '🟢 شروع'],
                    ['text' => '📖 راهنما'],
                ],
                [
                    ['text' => '📋 وضعیت'],
                    ['text' => '📞 پشتیبانی'],
                ],
                [
                    ['text' => '❌ لغو عملیات'],
                ],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * Persistent menu keyboard shown after the user has completed onboarding
     * or when responding to informational commands.
     */
    private function menuKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => '🟢 شروع'],
                    ['text' => '📖 راهنما'],
                ],
                [
                    ['text' => '📋 وضعیت'],
                    ['text' => '📞 پشتیبانی'],
                ],
                [
                    ['text' => '❌ لغو عملیات'],
                ],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * Two-button reply keyboard for choosing the preferred platform.
     */
    private function preferenceKeyboard(string $platformA, string $platformB): array
    {
        $labelA = self::PLATFORM_LABELS[$platformA] ?? $platformA;
        $labelB = self::PLATFORM_LABELS[$platformB] ?? $platformB;

        return [
            'keyboard' => [
                [
                    ['text' => "⭐ {$labelA}"],
                    ['text' => "⭐ {$labelB}"],
                ],
            ],
            'resize_keyboard'   => true,
            'one_time_keyboard' => true,
        ];
    }

    /**
     * Map the user's button tap (or typed text) back to a platform key.
     */
    private function parsePreferenceChoice(string $text): ?string
    {
        $text = trim($text);

        foreach (self::PLATFORM_LABELS as $platform => $label) {
            if ($text === "⭐ {$label}" || mb_strtolower($text) === mb_strtolower($label)) {
                return $platform;
            }
        }

        // Also accept the English names.
        $lower = strtolower($text);
        if (in_array($lower, ['telegram', 'bale'], true)) {
            return $lower;
        }

        return null;
    }

    // ------------------------------------------------------------------
    //  Phone helpers — normalization lives in the shared PhoneNormalizer
    //  (see PhoneNormalizer.php) so every send path accepts the same input.
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    //  DB helpers
    // ------------------------------------------------------------------

    /**
     * Insert or update the contact + channel, now including username and
     * display_name from the messenger profile.
     */
    private function linkContact(
        string  $phone,
        string  $chatId,
        ?string $username    = null,
        ?string $displayName = null
    ): void {
        $db = Db::conn();

        $db->begin_transaction();
        try {
            // Upsert contact — also update display_name if we have one.
            if ($displayName !== null) {
                $stmt = $db->prepare(
                    'INSERT INTO contacts (phone_number, display_name) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), display_name = VALUES(display_name)'
                );
                $stmt->bind_param('ss', $phone, $displayName);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO contacts (phone_number) VALUES (?)
                     ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
                );
                $stmt->bind_param('s', $phone);
            }
            $stmt->execute();
            $contactId = $db->insert_id;
            $stmt->close();

            // Upsert channel — also save username.
            $stmt = $db->prepare(
                'INSERT INTO contact_channels (contact_id, platform, chat_id, username)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE chat_id = VALUES(chat_id), username = VALUES(username)'
            );
            $stmt->bind_param('isss', $contactId, $this->platform, $chatId, $username);
            $stmt->execute();
            $stmt->close();

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Returns the other platform key if the given phone is already linked
     * there, or null if this is the only platform.
     */
    private function getOtherLinkedPlatform(string $phone): ?string
    {
        $db = Db::conn();
        $stmt = $db->prepare(
            'SELECT cc.platform
             FROM contact_channels cc
             JOIN contacts c ON c.id = cc.contact_id
             WHERE c.phone_number = ? AND cc.platform != ?
             LIMIT 1'
        );
        $stmt->bind_param('ss', $phone, $this->platform);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result['platform'] ?? null;
    }

    /**
     * Look up the phone number linked to a chat_id on the current platform.
     */
    private function getPhoneByChatId(string $chatId): ?string
    {
        $db = Db::conn();
        $stmt = $db->prepare(
            'SELECT c.phone_number
             FROM contacts c
             JOIN contact_channels cc ON cc.contact_id = c.id
             WHERE cc.platform = ? AND cc.chat_id = ?
             LIMIT 1'
        );
        $stmt->bind_param('ss', $this->platform, $chatId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result['phone_number'] ?? null;
    }

    private function setPreferredPlatform(string $phone, string $platform): void
    {
        $db = Db::conn();
        $stmt = $db->prepare(
            'UPDATE contacts SET preferred_platform = ? WHERE phone_number = ?'
        );
        $stmt->bind_param('ss', $platform, $phone);
        $stmt->execute();
        $stmt->close();
    }

    // ------------------------------------------------------------------
    //  Session state
    // ------------------------------------------------------------------

    private function getState(string $chatId): string
    {
        $db = Db::conn();
        $stmt = $db->prepare('SELECT state FROM bot_sessions WHERE platform = ? AND chat_id = ?');
        $stmt->bind_param('ss', $this->platform, $chatId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result['state'] ?? 'new';
    }

    private function setState(string $chatId, string $state): void
    {
        $db = Db::conn();
        $stmt = $db->prepare(
            'INSERT INTO bot_sessions (platform, chat_id, state) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE state = VALUES(state)'
        );
        $stmt->bind_param('sss', $this->platform, $chatId, $state);
        $stmt->execute();
        $stmt->close();
    }
}
