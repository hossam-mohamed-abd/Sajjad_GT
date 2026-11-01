<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'logout') {
        if (!empty($_COOKIE['worker_device'])) {
            $q = db()->prepare('DELETE FROM worker_devices WHERE token_hash=?'); $q->execute([hash('sha256', $_COOKIE['worker_device'])]);
            setcookie('worker_device', '', ['expires'=>time()-3600,'path'=>cookie_path(),'httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
        }
        $_SESSION = []; session_destroy(); redirect('index.php');
    }
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $q = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'); $q->execute([$ipHash]);
    if ((int)$q->fetchColumn() >= 10) { flash('Too many attempts. Please wait 15 minutes.', 'error'); redirect('index.php'); }
    $q = db()->prepare('INSERT INTO login_attempts (ip_hash) VALUES (?)'); $q->execute([$ipHash]);
    $q = db()->prepare("SELECT * FROM users WHERE email=? AND role='admin' AND active=1 LIMIT 1"); $q->execute([trim((string)($_POST['email'] ?? ''))]); $user = $q->fetch();
    if ($user && $user['password_hash'] && password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
        session_regenerate_id(true); $_SESSION['user_id'] = $user['id'];
        $q = db()->prepare('DELETE FROM login_attempts WHERE ip_hash=?'); $q->execute([$ipHash]);
        redirect('admin.php');
    }
    flash('Incorrect email or password.', 'error'); redirect('index.php');
}
$user = current_user(); if ($user) redirect($user['role'] === 'admin' ? 'admin.php' : 'worker.php');
page_start('Sign in');
?>
<div class="auth-card"><img class="auth-logo" src="assets/global-trading.png" alt="Global Trading"><div class="eyebrow">WELCOME TO YOUR WORKSPACE</div><h1>Good to see you.</h1><p>Sign in to manage your field operations, all in one place.</p><form method="post" class="stack"><?=csrf_field()?><label>Email address<input type="email" name="email" placeholder="you@company.com" required autocomplete="username"></label><label>Password<input type="password" name="password" placeholder="Enter your password" required autocomplete="current-password"></label><button class="button primary wide">Sign in <span>→</span></button></form><div class="auth-foot">Workers: open the secure device link provided by your administrator.</div></div>
<?php page_end(false); ?>
