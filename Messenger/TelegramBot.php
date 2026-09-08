<?php

require_once __DIR__ . '/AbstractHttpBotClient.php';

class TelegramBot extends AbstractHttpBotClient
{
    protected function apiUrl(string $method): string
    {
        return "https://api.telegram.org/bot{$this->token}/{$method}";
    }
}
