<?php

interface MessengerClientInterface
{
    /**
     * @param string $chatId
     * @param string $text
     * @param array  $extra Optional extra params (e.g. reply_markup)
     * @return array Decoded JSON response
     */
    public function sendMessage(string $chatId, string $text, array $extra = []): array;

    /**
     * @param string $chatId
     * @param string|CURLFile $photo URL, local file path, or CURLFile
     * @param string|null $caption Optional caption
     * @param array  $extra Optional extra params
     * @return array Decoded JSON response
     */
    public function sendPhoto(string $chatId, string|CURLFile $photo, ?string $caption = null, array $extra = []): array;

    /**
     * @param string $chatId
     * @param string|CURLFile $document URL, local file path, or CURLFile
     * @param string|null $caption Optional caption
     * @param array  $extra Optional extra params
     * @return array Decoded JSON response
     */
    public function sendDocument(string $chatId, string|CURLFile $document, ?string $caption = null, array $extra = []): array;
}
