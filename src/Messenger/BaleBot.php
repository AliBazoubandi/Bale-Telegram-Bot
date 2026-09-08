<?php

require_once __DIR__ . '/AbstractHttpBotClient.php';

class BaleBot extends AbstractHttpBotClient
{
    protected function apiUrl(string $method): string
    {
        return "https://tapi.bale.ai/bot{$this->token}/{$method}";
    }
}
