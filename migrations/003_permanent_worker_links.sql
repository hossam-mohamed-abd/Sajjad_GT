-- Back up the database before running. Existing valid links/devices are preserved.
-- NULL means no server-side expiry; used_at still protects legacy timed invitations.
ALTER TABLE worker_invites MODIFY expires_at DATETIME NULL DEFAULT NULL;
ALTER TABLE worker_devices MODIFY expires_at DATETIME NULL DEFAULT NULL;

-- Preserve only the most recently issued, non-revoked legacy link per worker:
-- an unused link must still be valid; a used link must have an active device.
-- Older/revoked/expired unused links are not reactivated.
UPDATE worker_invites i
JOIN (SELECT user_id, MAX(id) AS latest_id FROM worker_invites GROUP BY user_id) latest ON latest.latest_id = i.id
SET i.expires_at = NULL
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'migration_003_permanent_worker_links')
  AND i.expires_at IS NOT NULL
  AND ((i.used_at IS NULL AND i.expires_at > NOW())
       OR (i.used_at IS NOT NULL AND EXISTS (
           SELECT 1 FROM worker_devices d WHERE d.user_id = i.user_id AND d.expires_at > NOW()
       )));

-- Only currently active devices become non-expiring. Never revive expired devices.
UPDATE worker_devices SET expires_at = NULL
WHERE expires_at > NOW()
  AND NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'migration_003_permanent_worker_links');
INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('migration_003_permanent_worker_links', 'applied');
