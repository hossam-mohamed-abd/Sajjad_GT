-- Run once on an existing installation, after taking a database backup.
-- Safe to run again: an administrator's saved address will not be overwritten.
CREATE TABLE IF NOT EXISTS app_settings (
 setting_key VARCHAR(100) PRIMARY KEY,
 setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO app_settings (setting_key, setting_value)
VALUES ('notification_email', 'hossam545mohamed@gmail.com');
