<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('admin'); if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin.php'); check_csrf();
$action = (string)($_POST['action'] ?? ''); $pdo = db();
try {
    if ($action === 'worker_audio') {
        $enabled = (string)($_POST['enabled'] ?? '');
        if (!in_array($enabled, ['0', '1'], true)) throw new RuntimeException('Invalid sound setting.');
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? FOR UPDATE'); $q->execute(['worker_audio_enabled']); $previous=$q->fetchColumn();
        $q=$pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'); $q->execute(['worker_audio_enabled', $enabled]);
        audit((int)$user['id'], null, 'worker audio updated', 'From: ' . ($previous === false ? 'default on' : ($previous === '1' ? 'on' : 'off')) . '; to: ' . ($enabled === '1' ? 'on' : 'off'));
        $pdo->commit(); flash('Worker voice instructions are ' . ($enabled === '1' ? 'on' : 'off') . '. Open or refresh the worker page to apply.');
    } elseif ($action === 'notification_email') {
        $email = trim((string)($_POST['notification_email'] ?? ''));
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid notification email address.');
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? FOR UPDATE'); $q->execute(['notification_email']); $old = $q->fetchColumn();
        $q = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'); $q->execute(['notification_email', $email]);
        audit((int)$user['id'], null, 'notification email updated', 'From: ' . ($old ?: '(not set)') . '; to: ' . $email);
        $pdo->commit(); flash('Notification email updated. New reports will be sent to this address.');
    } elseif ($action === 'worker_add') {
        $name = trim((string)($_POST['name'] ?? '')); $email = trim((string)($_POST['email'] ?? ''));
        if ($name === '' || strlen($name)>120 || ($email && !filter_var($email,FILTER_VALIDATE_EMAIL))) throw new RuntimeException('Enter a valid worker name and optional email.');
        $q = $pdo->prepare("INSERT INTO users (name,email,role) VALUES (?,?,'worker')"); $q->execute([$name,$email ?: null]);
        audit((int)$user['id'], null, 'worker added', $name); flash('Worker added. Create a device link to give them access.');
    } elseif ($action === 'invite') {
        $id = (int)($_POST['worker_id'] ?? 0); $q = $pdo->prepare("SELECT name FROM users WHERE id=? AND role='worker' AND active=1"); $q->execute([$id]); $name = $q->fetchColumn();
        if (!$name) throw new RuntimeException('Worker not found.');
        $token = bin2hex(random_bytes(32)); $pdo->beginTransaction();
        $q=$pdo->prepare('DELETE FROM worker_invites WHERE user_id=? AND used_at IS NULL'); $q->execute([$id]);
        $q=$pdo->prepare('INSERT INTO worker_invites (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 7 DAY))'); $q->execute([$id,hash('sha256',$token)]);
        audit((int)$user['id'],null,'device invitation created',(string)$name); $pdo->commit();
        $_SESSION['invite_link'] = app_url('invite.php?token=' . $token); flash('One-time link created. Open it on the worker’s own device within 7 days.');
    } elseif ($action === 'revoke') {
        $id=(int)($_POST['worker_id'] ?? 0); $q=$pdo->prepare("SELECT name FROM users WHERE id=? AND role='worker'"); $q->execute([$id]); $name=$q->fetchColumn(); if (!$name) throw new RuntimeException('Worker not found.');
        $pdo->beginTransaction(); $q=$pdo->prepare('DELETE FROM worker_devices WHERE user_id=?'); $q->execute([$id]); $q=$pdo->prepare('DELETE FROM worker_invites WHERE user_id=? AND used_at IS NULL'); $q->execute([$id]); audit((int)$user['id'],null,'worker devices revoked',(string)$name); $pdo->commit(); flash('All worker devices and unused links have been revoked.');
    } elseif ($action === 'project_add' || $action === 'project_update') {
        $name=trim((string)($_POST['name'] ?? '')); $client=trim((string)($_POST['client'] ?? '')); $total=filter_var($_POST['total_units'] ?? null,FILTER_VALIDATE_INT); $done=filter_var($_POST['completed_units'] ?? null,FILTER_VALIDATE_INT);
        if (!$name || strlen($name)>160 || strlen($client)>160 || $total===false || $done===false || $total<0 || $done<0 || $done>$total || $total>1000000) throw new RuntimeException('Check project details. Completed units cannot exceed total units.');
        if ($action==='project_add') { $q=$pdo->prepare('INSERT INTO projects (name,client,total_units,completed_units) VALUES (?,?,?,?)'); $q->execute([$name,$client,$total,$done]); } else { $q=$pdo->prepare('UPDATE projects SET name=?,client=?,total_units=?,completed_units=? WHERE id=?'); $q->execute([$name,$client,$total,$done,(int)($_POST['project_id'] ?? 0)]); }
        audit((int)$user['id'],null,$action==='project_add'?'project added':'project updated', $name . ": $done / $total"); flash('Project saved.');
    } else throw new RuntimeException('Unknown action.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash($e instanceof RuntimeException ? $e->getMessage() : 'Could not complete the action. Check for duplicate values.', 'error');
}
redirect('admin.php' . ($action === 'worker_audio' ? '#sound-settings' : ($action === 'notification_email' ? '#notifications' : (in_array($action,['worker_add','invite','revoke'],true) ? '#team' : '#projects'))));
