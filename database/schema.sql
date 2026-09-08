-- Notifier DB schema (MySQL)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS contacts (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number        VARCHAR(20) NOT NULL,      -- normalized, e.g. 989121234567
    display_name        VARCHAR(191) NULL,          -- first_name + last_name from messenger
    preferred_platform  VARCHAR(20) NULL,           -- 'telegram' or 'bale'; NULL = no preference
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contacts_phone (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Links a contact to a chat on a given platform.
-- A contact can have a row for 'telegram', a row for 'bale', or both.
CREATE TABLE IF NOT EXISTS contact_channels (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id   BIGINT UNSIGNED NOT NULL,
    platform     ENUM('telegram', 'bale') NOT NULL,
    chat_id      VARCHAR(64) NOT NULL,       -- the platform's conversation id
    username     VARCHAR(191) NULL,          -- @username from the platform
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contact_channels_contact
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    UNIQUE KEY uq_contact_platform (contact_id, platform),   -- one link per platform per contact
    UNIQUE KEY uq_platform_chat (platform, chat_id)          -- a chat_id maps to exactly one contact
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tiny conversation state machine so the webhook knows what it's waiting for.
CREATE TABLE IF NOT EXISTS bot_sessions (
    platform    ENUM('telegram', 'bale') NOT NULL,
    chat_id     VARCHAR(64) NOT NULL,
    state       VARCHAR(32) NOT NULL DEFAULT 'new',   -- new | awaiting_phone | awaiting_preference | linked
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (platform, chat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- External apps that are allowed to send notifications through this system.
-- Each app gets its own app_key for API authentication.
CREATE TABLE IF NOT EXISTS sender_apps (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    app_name      VARCHAR(191) NOT NULL,
    app_key       VARCHAR(64)  NOT NULL,
    is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_app_name (app_name),
    UNIQUE KEY uq_app_key (app_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_logs (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_app_id BIGINT UNSIGNED NOT NULL,
    contact_id    BIGINT UNSIGNED NOT NULL,
    platform      VARCHAR(20)     NOT NULL, -- 'telegram' or 'bale'
    phone_number  VARCHAR(20)     NOT NULL,
    message       TEXT            NOT NULL,
    link          VARCHAR(2048)   NULL,
    attachment_url VARCHAR(2048)  NULL,
    attachment_type VARCHAR(32)   NULL,     -- 'photo' or 'document'
    status        ENUM('sent', 'failed') NOT NULL,
    error_message TEXT            NULL,
    sent_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (sender_app_id) REFERENCES sender_apps(id),
    FOREIGN KEY (contact_id) REFERENCES contacts(id),
    INDEX idx_phone (phone_number),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(64)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(191) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_throttles (
    ip           VARCHAR(45)  NOT NULL PRIMARY KEY,
    failures     INT UNSIGNED NOT NULL DEFAULT 1,
    last_attempt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    locked_until DATETIME     NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
