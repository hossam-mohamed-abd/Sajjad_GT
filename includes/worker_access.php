<?php
declare(strict_types=1);

/** Timed legacy links are single-use; permanent links are reusable until deleted. */
function worker_invite_lookup_sql(): string {
    return "SELECT i.id,i.user_id,i.expires_at,u.name FROM worker_invites i JOIN users u ON u.id=i.user_id WHERE i.token_hash=? AND (i.expires_at IS NULL OR (i.used_at IS NULL AND i.expires_at>NOW())) AND u.active=1 AND u.role='worker'";
}
function worker_device_lookup_sql(): string {
    return "SELECT d.user_id FROM worker_devices d JOIN users u ON u.id=d.user_id WHERE d.token_hash=? AND (d.expires_at IS NULL OR d.expires_at>NOW()) AND u.active=1 AND u.role='worker'";
}
function worker_session_device_sql(): string {
    return 'SELECT id FROM worker_devices WHERE user_id=? AND token_hash=? AND (expires_at IS NULL OR expires_at>NOW())';
}
