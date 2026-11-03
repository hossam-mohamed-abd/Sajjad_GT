-- Run after 001_notification_email.sql. Safe to rerun without changing the saved choice.
CREATE TABLE IF NOT EXISTS app_settings (
 setting_key VARCHAR(100) PRIMARY KEY,
 setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO app_settings (setting_key, setting_value)
VALUES ('worker_audio_enabled', '1');
