<?php
require __DIR__ . '/includes/bootstrap.php';
header('Cache-Control: no-store');
$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) { http_response_code(400); exit('Invalid access link.'); }
// Permanent links have no expiry and can be reused. Timed legacy links remain single-use.
$sql = worker_invite_lookup_sql();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $q=db()->prepare($sql); $q->execute([hash('sha256',$token)]); $invite=$q->fetch();
    if (!$invite) { http_response_code(403); exit('This access link is invalid, expired or stopped. Ask your administrator for a new one.'); }
    $current=current_user();
    if ($current && $current['role']==='worker' && (int)$current['id']===(int)$invite['user_id']) redirect('worker.php');
    $expiry=$invite['expires_at'];
    page_start('Connect your device');
    ?>
    <div class="auth-card"><img class="auth-logo" src="assets/global-trading.png" alt="Global Trading"><div class="eyebrow">SECURE DEVICE SETUP</div><h1>Hello, <?=e($invite['name'])?>.</h1><p>Connect this device to start sending field reports. Use only your own device.</p><form method="post"><?=csrf_field()?><input type="hidden" name="token" value="<?=e($token)?>"><button class="button primary wide">Connect my device <span>→</span></button></form><div class="auth-foot"><?php if ($expiry === null): ?>This access link stays active until your administrator replaces or stops it.<?php else: ?>This older one-time link expires on <?=e(date('d M Y, H:i',strtotime($expiry)))?>.<?php endif; ?></div></div>
    <?php
    page_end(false); exit;
}
check_csrf();
$pdo=db(); $pdo->beginTransaction();
try {
    // Lock the link while activating: replacement or revocation cannot race an activation.
    $q=$pdo->prepare($sql.' FOR UPDATE'); $q->execute([hash('sha256',$token)]); $invite=$q->fetch();
    if (!$invite) { $pdo->rollBack(); http_response_code(403); exit('This access link is invalid, expired or stopped. Ask your administrator for a new one.'); }
    $device=bin2hex(random_bytes(32));
    $q=$pdo->prepare('INSERT INTO worker_devices (user_id,token_hash,expires_at) VALUES (?,?,NULL)'); $q->execute([$invite['user_id'],hash('sha256',$device)]);
    if ($invite['expires_at'] !== null) { $q=$pdo->prepare('UPDATE worker_invites SET used_at=NOW() WHERE id=?'); $q->execute([$invite['id']]); }
    $pdo->commit();
    set_worker_device_cookie($device);
    session_regenerate_id(true); $_SESSION['user_id']=(int)$invite['user_id']; $_SESSION['device_hash']=hash('sha256',$device); redirect('worker.php');
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); http_response_code(500); exit('Could not activate this device.'); }
