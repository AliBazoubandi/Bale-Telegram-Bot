<?php

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/PhoneNormalizer.php';
require_once __DIR__ . '/Messenger/MessengerFactory.php';

/**
 * Sends notifications (text, photos, documents/PDFs) to a contact's linked
 * platforms and logs every attempt in the message_logs table.
 *
 * The preferred platform (if set) is always contacted first.
 */
class NotificationSender
{
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

                if ($attachment !== null) {
                    if ($normalizedType === 'photo') {
                        $bot->sendPhoto($channel['chat_id'], $attachment, $finalText);
                    } else {
                        $bot->sendDocument($channel['chat_id'], $attachment, $finalText);
                    }
                } else {
                    $bot->sendMessage($channel['chat_id'], $finalText);
                }

                $this->logMessage(
                    $appId, $contactId, $channel['platform'],
                    $phone, $message, $link, $attachment, $normalizedType, 'sent', null
                );

                $results[] = [
                    'platform'   => $channel['platform'],
                    'status'     => 'sent',
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
