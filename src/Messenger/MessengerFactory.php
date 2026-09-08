<?php

require_once __DIR__ . '/BaleBot.php';
require_once __DIR__ . '/TelegramBot.php';

class MessengerFactory
{
    public static function make(string $platform): MessengerClientInterface
    {
        switch ($platform) {
            case 'bale':
                return new BaleBot(getenv('BALE_BOT_TOKEN') ?: '');
            case 'telegram':
                return new TelegramBot(getenv('TELEGRAM_BOT_TOKEN') ?: '');
            default:
                throw new InvalidArgumentException("Unknown platform: {$platform}");
        }
    }
}
