<?php
declare(strict_types=1);

$root = dirname(__DIR__);
if (is_file($root . '/.env')) {
    foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) $value = substr($value, 1, -1);
            $_ENV[$key] = $value;
        }
    }
}
function envv(string $key, string $default = ''): string { return (string)($_ENV[$key] ?? $default); }
date_default_timezone_set(envv('APP_TIMEZONE', 'Asia/Dubai'));
function cookie_path(): string { $path = parse_url(envv('APP_URL', 'http://localhost/Sajjad_GT'), PHP_URL_PATH) ?: '/'; return rtrim($path, '/') . '/'; }
if (PHP_SAPI !== 'cli') {
    session_name('GT_FIELD_SESSION');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => cookie_path()]);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
}
function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $dsn = 'mysql:host=' . envv('DB_HOST', '127.0.0.1') . ';port=' . envv('DB_PORT', '3306') . ';dbname=' . envv('DB_DATABASE', 'sajjad_gt') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, envv('DB_USERNAME', 'root'), envv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $offset = date('P');
        $pdo->exec("SET time_zone = '" . $offset . "'");
    }
    return $pdo;
}
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . $path); exit; }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function check_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid session token. Please refresh and try again.'); } }
function flash(string $message, string $kind = 'success'): void { $_SESSION['flash'] = [$message, $kind]; }
function current_user(): ?array {
    if (empty($_SESSION['user_id']) && !empty($_COOKIE['worker_device'])) {
        $token = (string)$_COOKIE['worker_device'];
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            $q = db()->prepare("SELECT d.user_id FROM worker_devices d JOIN users u ON u.id=d.user_id WHERE d.token_hash=? AND d.expires_at>NOW() AND u.active=1 AND u.role='worker'");
            $q->execute([hash('sha256', $token)]); $id = $q->fetchColumn();
            if ($id) { session_regenerate_id(true); $_SESSION['user_id'] = (int)$id; $_SESSION['device_hash'] = hash('sha256', $token); }
        }
    }
    if (empty($_SESSION['user_id'])) return null;
    $q = db()->prepare('SELECT id,name,email,role,active FROM users WHERE id=? AND active=1'); $q->execute([$_SESSION['user_id']]);
    $user = $q->fetch() ?: null;
    if ($user && $user['role'] === 'worker') {
        $deviceHash = $_SESSION['device_hash'] ?? '';
        $device = db()->prepare('SELECT id FROM worker_devices WHERE user_id=? AND token_hash=? AND expires_at>NOW()');
        $device->execute([$user['id'], $deviceHash]);
        if (!$device->fetchColumn()) { unset($_SESSION['user_id'], $_SESSION['device_hash']); return null; }
    }
    return $user;
}
function require_role(string $role): array {
    $u = current_user(); if (!$u || $u['role'] !== $role) { http_response_code(403); exit('Access denied.'); } return $u;
}
function audit(int $actor, ?int $report, string $action, string $details = ''): void {
    $q = db()->prepare('INSERT INTO audit_log (actor_id,report_id,action,details) VALUES (?,?,?,?)'); $q->execute([$actor,$report,$action,$details]);
}
function app_url(string $path = ''): string { return rtrim(envv('APP_URL', 'http://localhost/Sajjad_GT'), '/') . '/' . ltrim($path, '/'); }
function page_start(string $title, ?array $user = null, string $active = ''): void {
    $msg = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#063675"><title>'.e($title).' | Global Trading</title><link rel="stylesheet" href="assets/style.css"><script defer src="assets/app.js"></script></head><body>';
    if ($user) {
        echo '<div class="app-shell"><aside class="sidebar"><a class="brand" href="'.($user['role']==='admin'?'admin.php':'worker.php').'"><img src="assets/global-trading.png" alt="Global Trading"><span>FIELD REPORTS<small>OPERATIONS HUB</small></span></a><div class="side-label">WORKSPACE</div><nav>';
        if ($user['role'] === 'admin') echo '<a class="'.($active==='dashboard'?'active':'').'" href="admin.php"><span>▦</span> Overview</a><a href="admin.php#reports"><span>▤</span> Reports</a><a href="admin.php#projects"><span>◈</span> Projects</a><a href="admin.php#team"><span>♙</span> Team</a><a href="admin.php#notifications"><span>✉</span> Notifications</a>';
        else echo '<a class="active" href="worker.php"><span>▦</span> My reports</a>';
        echo '</nav><div class="sidebar-bottom"><div class="user-badge"><span class="avatar">'.e(strtoupper(substr($user['name'],0,1))).'</span><div><strong>'.e($user['name']).'</strong><small>'.e(ucfirst($user['role'])).'</small></div></div><form method="post" action="index.php">'.csrf_field().'<input type="hidden" name="action" value="logout"><button class="logout">↗ &nbsp; Sign out</button></form></div></aside><div class="main-wrap"><header class="topbar"><div><span class="top-eyebrow">GLOBAL TRADING</span><strong>'.e($title).'</strong></div><div class="top-right"><span class="live-dot"></span> FIELD OPERATIONS <span class="top-date">'.e(date('d M Y')).'</span></div></header><main class="content">';
    } else echo '<main class="auth-wrap">';
    if ($msg) echo '<div class="notice '.e($msg[1]).'">'.e($msg[0]).'</div>';
}
function page_end(bool $logged = true): void { echo $logged ? '</main></div></div></body></html>' : '</main></body></html>'; }
