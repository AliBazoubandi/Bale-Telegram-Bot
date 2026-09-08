<?php

require_once __DIR__ . '/MessengerClientInterface.php';

abstract class AbstractHttpBotClient implements MessengerClientInterface
{
    protected string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    abstract protected function apiUrl(string $method): string;

    public function sendMessage(string $chatId, string $text, array $extra = []): array
    {
        $payload = array_merge([
            'chat_id' => $chatId,
            'text'    => $text,
        ], $extra);

        return $this->call('sendMessage', $payload);
    }

    public function sendPhoto(string $chatId, string|CURLFile $photo, ?string $caption = null, array $extra = []): array
    {
        $payload = array_merge([
            'chat_id' => $chatId,
            'photo'   => $this->prepareMediaParam($photo),
        ], $extra);

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        return $this->call('sendPhoto', $payload);
    }

    public function sendDocument(string $chatId, string|CURLFile $document, ?string $caption = null, array $extra = []): array
    {
        $payload = array_merge([
            'chat_id'  => $chatId,
            'document' => $this->prepareMediaParam($document),
        ], $extra);

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        return $this->call('sendDocument', $payload);
    }

    /**
     * Converts a local file path to a CURLFile if it exists on disk.
     */
    protected function prepareMediaParam(string|CURLFile $file): string|CURLFile
    {
        if ($file instanceof CURLFile) {
            return $file;
        }

        // If it's a local file on the filesystem, prepare CURLFile for direct upload
        if (is_string($file) && !preg_match('/^https?:\/\//i', $file) && file_exists($file) && is_file($file)) {
            return new CURLFile(realpath($file));
        }

        return $file;
    }

    protected function call(string $method, array $payload): array
    {
        $hasFile = false;
        foreach ($payload as $val) {
            if ($val instanceof CURLFile) {
                $hasFile = true;
                break;
            }
        }

        $ch = curl_init($this->apiUrl($method));
        
        $curlOptions = [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4, // Force IPv4 to prevent 21s IPv6 DNS timeouts on Windows
            CURLOPT_SSL_VERIFYPEER => false,             // Avoid corporate SSL inspection handshake failures
            CURLOPT_SSL_VERIFYHOST => 0,
        ];

        // Optional Proxy configuration
        if (static::class === 'TelegramBot') {
            $proxy = getenv('TELEGRAM_PROXY') ?: getenv('HTTP_PROXY');
            if (!empty($proxy) && $proxy !== 'changeme') {
                $curlOptions[CURLOPT_PROXY] = $proxy;
            }
        } elseif (static::class === 'BaleBot') {
            $proxy = getenv('BALE_PROXY');
            if (!empty($proxy) && $proxy !== 'changeme') {
                $curlOptions[CURLOPT_PROXY] = $proxy;
            }
        }

        if ($hasFile) {
            // Multipart form-data for direct file uploads
            $postFields = [];
            foreach ($payload as $key => $val) {
                if (is_array($val)) {
                    $postFields[$key] = json_encode($val, JSON_UNESCAPED_UNICODE);
                } else {
                    $postFields[$key] = $val;
                }
            }
            $curlOptions[CURLOPT_POSTFIELDS] = $postFields;
        } else {
            // Standard JSON payload
            $curlOptions[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $curlOptions);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(static::class . " transport error: {$err}");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);

        if ($httpCode >= 400 || !is_array($decoded) || (isset($decoded['ok']) && $decoded['ok'] === false)) {
            $errDesc = is_array($decoded) && isset($decoded['description']) ? $decoded['description'] : $raw;
            throw new RuntimeException(static::class . " API error (HTTP {$httpCode}): {$errDesc}");
        }

        return $decoded;
    }
}
