-- Migration: add attachment columns to message_logs
SET @col_exists_url = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'message_logs'
      AND COLUMN_NAME  = 'attachment_url'
);
SET @sql_url = IF(@col_exists_url = 0,
    'ALTER TABLE message_logs ADD COLUMN attachment_url VARCHAR(2048) NULL AFTER link',
    'SELECT 1');
PREPARE stmt_url FROM @sql_url;
EXECUTE stmt_url;
DEALLOCATE PREPARE stmt_url;

SET @col_exists_type = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'message_logs'
      AND COLUMN_NAME  = 'attachment_type'
);
SET @sql_type = IF(@col_exists_type = 0,
    'ALTER TABLE message_logs ADD COLUMN attachment_type VARCHAR(32) NULL AFTER attachment_url',
    'SELECT 1');
PREPARE stmt_type FROM @sql_type;
EXECUTE stmt_type;
DEALLOCATE PREPARE stmt_type;
