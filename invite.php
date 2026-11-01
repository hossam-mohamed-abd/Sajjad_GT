<?php
require __DIR__ . '/includes/bootstrap.php';
$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) { http_response_code(400); exit('Invalid invitation.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $q=db()->prepare("SELECT u.name FROM worker_invites i JOIN users u ON u.id=i.user_id WHERE i.token_hash=? AND i.used_at IS NULL AND i.expires_at>NOW() AND u.active=1 AND u.role='worker'");
    $q->execute([hash('sha256',$token)]); $name=$q->fetchColumn();
    if (!$name) { http_response_code(403); exit('This invitation is invalid, expired or already used. Ask your administrator for a new one.'); }
    page_start('Connect your device');
    ?>
    <div class="auth-card"><img class="auth-logo" src="assets/global-trading.png" alt="Global Trading"><div class="eyebrow">SECURE DEVICE SETUP</div><h1>Hello, <?=e((string)$name)?>.</h1><p>Connect this device to start sending field reports. Use only your own device.</p><form method="post"><?=csrf_field()?><input type="hidden" name="token" value="<?=e($token)?>"><button class="button primary wide">Connect my device <span>→</span></button></form><div class="auth-foot">This one-time link expires in 24 hours.</div></div>
    <?php
    page_end(false); exit;
}
check_csrf();
$pdo = db(); $pdo->beginTransaction();
try {
    $q = $pdo->prepare("SELECT i.id,i.user_id FROM worker_invites i JOIN users u ON u.id=i.user_id WHERE i.token_hash=? AND i.used_at IS NULL AND i.expires_at>NOW() AND u.active=1 AND u.role='worker' FOR UPDATE");
    $q->execute([hash('sha256', $token)]); $invite = $q->fetch();
    if (!$invite) { $pdo->rollBack(); http_response_code(403); exit('This invitation is invalid, expired or already used. Ask your administrator for a new one.'); }
    $device = bin2hex(random_bytes(32));
    $q = $pdo->prepare('INSERT INTO worker_devices (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 90 DAY))'); $q->execute([$invite['user_id'], hash('sha256', $device)]);
    $q = $pdo->prepare('UPDATE worker_invites SET used_at=NOW() WHERE id=?'); $q->execute([$invite['id']]);
    $pdo->commit();
    setcookie('worker_device', $device, ['expires'=>time()+90*86400,'path'=>cookie_path(),'httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
    session_regenerate_id(true); $_SESSION['user_id']=(int)$invite['user_id']; $_SESSION['device_hash']=hash('sha256', $device); redirect('worker.php');
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); http_response_code(500); exit('Could not activate this device.'); }
