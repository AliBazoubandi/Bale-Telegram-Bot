<?php

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/PhoneNormalizer.php';
require_once __DIR__ . '/Messenger/MessengerFactory.php';

/**
 * Sends notifications (text, photos, documents/PDFs) to a contact's linked
 * platforms and logs every attempt in the message_logs table.
 *
 * Supports automatic chunking for long text messages (> 4000 characters)
 * and decoupled media sending when captions exceed platform limits (1024 characters).
 *
 * The preferred platform (if set) is always contacted first.
 */
class NotificationSender
{
    /** Maximum length of a single text message chunk (Telegram/Bale limit is 4096). */
    public const MAX_MESSAGE_CHUNK_LENGTH = 4000;

    /** Maximum length of a media caption (Telegram/Bale limit is 1024). */
    public const MAX_CAPTION_LENGTH = 1024;

    /** Delay in microseconds between sequential message chunks (150ms). */
    private const CHUNK_SEND_DELAY_US = 150000;

    /**
     * Splits a long text into clean chunks avoiding breaking words or paragraphs.
     *
     * @param string $text
     * @param int    $maxChunkLength
     * @return array<int, string>
     */
    public static function splitMessage(string $text, int $maxChunkLength = self::MAX_MESSAGE_CHUNK_LENGTH): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        if (mb_strlen($text, 'UTF-8') <= $maxChunkLength) {
            return [$text];
        }

        $chunks = [];
        $remaining = $text;

        while (mb_strlen($remaining, 'UTF-8') > $maxChunkLength) {
            $candidate = mb_substr($remaining, 0, $maxChunkLength, 'UTF-8');
            $splitPos = false;

            // Search in candidate for preferred breakpoints (from back to front)
            // 1. Double line break (paragraphs)
            $pos = mb_strrpos($candidate, "\n\n", 0, 'UTF-8');
            if ($pos !== false && $pos >= (int) ($maxChunkLength * 0.4)) {
                $splitPos = $pos + 2;
            }

            // 2. Single line break
            if ($splitPos === false) {
                $pos = mb_strrpos($candidate, "\n", 0, 'UTF-8');
                if ($pos !== false && $pos >= (int) ($maxChunkLength * 0.4)) {
                    $splitPos = $pos + 1;
                }
            }

            // 3. Space or punctuation break
            if ($splitPos === false) {
                $pos = mb_strrpos($candidate, ' ', 0, 'UTF-8');
                if ($pos !== false && $pos >= (int) ($maxChunkLength * 0.4)) {
                    $splitPos = $pos + 1;
                }
            }

            // 4. Fallback: hard cut at maximum chunk length
            if ($splitPos === false) {
                $splitPos = $maxChunkLength;
            }

            $chunk = trim(mb_substr($remaining, 0, $splitPos, 'UTF-8'));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            $remaining = ltrim(mb_substr($remaining, $splitPos, null, 'UTF-8'));
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return $chunks;
    }

    /**
     * @param string      $phone          Raw or normalized phone number — local ('0912...'),
     *                                    international ('+98...', '0098...') and already-normalized
     *                                    ('98912...') formats are all accepted and normalized here.
     * @param int         $appId          The sender_apps.id of the calling application.
     * @param string      $message        The message body to send (or caption for media).
     * @param string|null $link           Optional URL to append to the message.
     * @param string|null $attachment     Optional URL or local file path for photo/document.
     * @param string|null $attachmentType Optional 'photo', 'document', 'pdf', etc. (auto-detected if null).
     *
     * @return array List of per-channel results.
     */
    public function sendToPhone(
        string  $phone,
        int     $appId,
        string  $message,
        ?string $link = null,
        ?string $attachment = null,
        ?string $attachmentType = null
    ): array {
        // Normalize once, before any DB lookup — accepts '09121234567',
        // '+989121234567', '00989121234567' and '989121234567' alike.
        // Unparseable input falls back to the raw value (same no_channel_linked
        // outcome as before).
        $phone = PhoneNormalizer::normalize($phone) ?? $phone;

        $channels  = $this->findChannels($phone);
        $preferred = $this->getPreferredPlatform($phone);
        $contactId = $this->getContactId($phone);

        if (empty($channels) || $contactId === null) {
            return [[
                'platform' => null,
                'status'   => 'no_channel_linked',
            ]];
        }

        // Sort so the preferred platform comes first.
        if ($preferred !== null) {
            usort($channels, function (array $a, array $b) use ($preferred) {
                return ($b['platform'] === $preferred) <=> ($a['platform'] === $preferred);
            });
        }

        // Build final text — append link if provided.
        $finalText = $message;
        if ($link !== null && $link !== '') {
            $finalText .= "\n\n🔗 {$link}";
        }

        // Determine media attachment type if an attachment is provided
        $normalizedType = null;
        if ($attachment !== null && trim($attachment) !== '') {
            $attachment = trim($attachment);
            $normalizedType = $this->resolveAttachmentType($attachment, $attachmentType);
        } else {
            $attachment = null;
        }

        $results = [];

        foreach ($channels as $channel) {
            try {
                $bot = MessengerFactory::make($channel['platform']);
                $chunksSent = 1;

                if ($attachment !== null) {
                    $textLength = mb_strlen($finalText, 'UTF-8');

                    if ($textLength <= self::MAX_CAPTION_LENGTH) {
                        // Caption fits within the 1024-char limit
                        if ($normalizedType === 'photo') {
                            $bot->sendPhoto($channel['chat_id'], $attachment, $finalText);
                        } else {
                            $bot->sendDocument($channel['chat_id'], $attachment, $finalText);
                        }
                    } else {
                        // Text exceeds caption limit: send media first, then send full text
                        if ($normalizedType === 'photo') {
                            $bot->sendPhoto($channel['chat_id'], $attachment);
                        } else {
                            $bot->sendDocument($channel['chat_id'], $attachment);
                        }

                        // Send full text sequentially (chunking if needed)
                        $chunksSent = $this->sendChunkedText($bot, $channel['chat_id'], $finalText);
                    }
                } else {
                    // Plain text message (chunked if > 4000 chars)
                    $chunksSent = $this->sendChunkedText($bot, $channel['chat_id'], $finalText);
                }

                $this->logMessage(
                    $appId, $contactId, $channel['platform'],
                    $phone, $message, $link, $attachment, $normalizedType, 'sent', null
                );

                $results[] = [
                    'platform'   => $channel['platform'],
                    'status'     => 'sent',
                    'chunks'     => $chunksSent,
                    'attachment' => $attachment ? $normalizedType : null,
                    'preferred'  => $channel['platform'] === $preferred,
                ];
            } catch (Throwable $e) {
                $this->logMessage(
                    $appId, $contactId, $channel['platform'],
                    $phone, $message, $link, $attachment, $normalizedType, 'failed', $e->getMessage()
                );

                $results[] = [
                    'platform'   => $channel['platform'],
                    'status'     => 'failed',
                    'attachment' => $attachment ? $normalizedType : null,
                    'preferred'  => $channel['platform'] === $preferred,
                    'error'      => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Sends a text message, automatically splitting it into chunks if it exceeds the limit.
     *
     * @param MessengerClientInterface $bot
     * @param string                   $chatId
     * @param string                   $text
     * @return int Number of chunks sent.
     */
    private function sendChunkedText(MessengerClientInterface $bot, string $chatId, string $text): int
    {
        $chunks = self::splitMessage($text);
        if (empty($chunks)) {
            return 0;
        }

        $total = count($chunks);
        foreach ($chunks as $idx => $chunk) {
            $bot->sendMessage($chatId, $chunk);
            if ($idx + 1 < $total) {
                usleep(self::CHUNK_SEND_DELAY_US);
            }
        }

        return $total;
    }

    /**
     * Resolves attachment type ('photo' or 'document') based on provided hint or file extension.
     */
    private function resolveAttachmentType(string $attachment, ?string $hintType): string
    {
        if ($hintType !== null && trim($hintType) !== '') {
            $lower = strtolower(trim($hintType));
            if (in_array($lower, ['photo', 'image', 'picture', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                return 'photo';
            }
            if (in_array($lower, ['document', 'doc', 'pdf', 'file'], true)) {
                return 'document';
            }
        }

        $path = parse_url($attachment, PHP_URL_PATH) ?? $attachment;
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true)) {
            return 'photo';
        }

        return 'document';
    }

    // ------------------------------------------------------------------
    //  DB helpers
    // ------------------------------------------------------------------

    /**
     * @return array<int, array{platform: string, chat_id: string}>
     */
    private function findChannels(string $phone): array
    {
        $db = Db::conn();
        $stmt = $db->prepare(
            'SELECT cc.platform, cc.chat_id
             FROM contact_channels cc
             JOIN contacts c ON c.id = cc.contact_id
             WHERE c.phone_number = ?'
        );
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $result = $stmt->get_result();

        $channels = [];
        while ($row = $result->fetch_assoc()) {
            $channels[] = $row;
        }
        $stmt->close();

        return $channels;
    }

    private function getPreferredPlatform(string $phone): ?string
    {
        $db = Db::conn();
        $stmt = $db->prepare(
            'SELECT preferred_platform FROM contacts WHERE phone_number = ?'
        );
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result['preferred_platform'] ?? null;
    }

    private function getContactId(string $phone): ?int
    {
        $db = Db::conn();
        $stmt = $db->prepare('SELECT id FROM contacts WHERE phone_number = ?');
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result ? (int) $result['id'] : null;
    }

    private function logMessage(
        int     $appId,
        int     $contactId,
        string  $platform,
        string  $phone,
        string  $message,
        ?string $link,
        ?string $attachmentUrl,
        ?string $attachmentType,
        string  $status,
        ?string $errorMessage
    ): void {
        $db = Db::conn();

        // Check if attachment columns exist for backward compatibility
        static $hasAttachmentColumns = null;
        if ($hasAttachmentColumns === null) {
            $colRes = $db->query("SHOW COLUMNS FROM message_logs LIKE 'attachment_url'");
            $hasAttachmentColumns = ($colRes && $colRes->num_rows > 0);
        }

        if ($hasAttachmentColumns) {
            $stmt = $db->prepare(
                'INSERT INTO message_logs
                    (sender_app_id, contact_id, platform, phone_number, message, link, attachment_url, attachment_type, status, error_message)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'iissssssss',
                $appId, $contactId, $platform, $phone, $message, $link, $attachmentUrl, $attachmentType, $status, $errorMessage
            );
        } else {
            $stmt = $db->prepare(
                'INSERT INTO message_logs
                    (sender_app_id, contact_id, platform, phone_number, message, link, status, error_message)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'iissssss',
                $appId, $contactId, $platform, $phone, $message, $link, $status, $errorMessage
            );
        }

        $stmt->execute();
        $stmt->close();
    }
}
