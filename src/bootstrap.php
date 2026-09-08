<?php

/**
 * Global Bootstrap File for SendMessageBots.
 *
 * Defines ROOT_PATH and initializes environment, database, and all core
 * services/utilities. Entry points (webhooks, API, admin, CLI tools)
 * include ONLY this file to obtain a fully configured runtime.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// 1. Core Environment Configuration
require_once ROOT_PATH . '/src/Env.php';
Env::load(ROOT_PATH . '/.env');

// 2. Database Layer
require_once ROOT_PATH . '/src/Db.php';

// 3. Shared Utilities & Domain Helpers
require_once ROOT_PATH . '/src/PhoneNormalizer.php';
require_once ROOT_PATH . '/src/Jalali.php';
require_once ROOT_PATH . '/src/AttachmentValidator.php';
require_once ROOT_PATH . '/src/WebhookGuard.php';

// 4. Messenger Subsystem
require_once ROOT_PATH . '/src/Messenger/MessengerClientInterface.php';
require_once ROOT_PATH . '/src/Messenger/AbstractHttpBotClient.php';
require_once ROOT_PATH . '/src/Messenger/TelegramBot.php';
require_once ROOT_PATH . '/src/Messenger/BaleBot.php';
require_once ROOT_PATH . '/src/Messenger/MessengerFactory.php';

// 5. Business Logic Services
require_once ROOT_PATH . '/src/NotificationSender.php';
require_once ROOT_PATH . '/src/ConversationHandler.php';
