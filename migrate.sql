-- Migration script for existing databases.
-- Run this once if you already have data; new installs use schema.sql instead.

-- 1. sender_apps table
CREATE TABLE IF NOT EXISTS sender_apps (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    app_name   VARCHAR(191) NOT NULL,
    app_key    VARCHAR(64)  NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_app_name (app_name),
    UNIQUE KEY uq_app_key (app_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. message_logs table
CREATE TABLE IF NOT EXISTS message_logs (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_app_id BIGINT UNSIGNED NOT NULL,
    contact_id    BIGINT UNSIGNED NOT NULL,
    platform      VARCHAR(20)     NOT NULL,
    phone_number  VARCHAR(20)     NOT NULL,
    message       TEXT            NOT NULL,
    link          VARCHAR(2048)   NULL,
    status        ENUM('sent','failed') NOT NULL,
    error_message TEXT            NULL,
    sent_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_app     FOREIGN KEY (sender_app_id) REFERENCES sender_apps(id),
    CONSTRAINT fk_log_contact FOREIGN KEY (contact_id)    REFERENCES contacts(id),
    INDEX idx_log_phone (phone_number),
    INDEX idx_log_app   (sender_app_id),
    INDEX idx_log_sent  (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Add preferred_platform to contacts (if not already present)
--    This is safe to run even if the column already exists thanks to the procedure.
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'contacts'
      AND COLUMN_NAME  = 'preferred_platform'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE contacts ADD COLUMN preferred_platform VARCHAR(20) NULL AFTER display_name',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
