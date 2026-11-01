<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require dirname(__DIR__).'/includes/worker_access.php';
function check(bool $ok, string $description): void { if (!$ok) { fwrite(STDERR,"FAIL: $description\n"); exit(1); } }
$db=new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
@$db->sqliteCreateFunction('NOW', fn()=>'2026-11-01 00:00:00');
$db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY, name TEXT, role TEXT, active INTEGER); CREATE TABLE worker_invites(id INTEGER PRIMARY KEY, user_id INTEGER, token_hash TEXT, expires_at TEXT, used_at TEXT); CREATE TABLE worker_devices(id INTEGER PRIMARY KEY, user_id INTEGER, token_hash TEXT, expires_at TEXT)");
$db->exec("INSERT INTO users VALUES (1,'Sajjad','worker',1),(2,'Inactive','worker',0),(3,'Admin','admin',1)");
$db->exec("INSERT INTO worker_invites VALUES (1,1,'permanent',NULL,NULL),(2,1,'permanent-used',NULL,'2026-10-01'),(3,1,'legacy-valid','2026-11-05',NULL),(4,1,'legacy-used','2026-11-05','2026-10-30'),(5,1,'legacy-expired','2026-10-30',NULL),(6,2,'disabled',NULL,NULL),(7,3,'admin',NULL,NULL)");
$db->exec("INSERT INTO worker_devices VALUES (1,1,'device-permanent',NULL),(2,1,'device-valid','2026-11-05'),(3,1,'device-expired','2026-10-30'),(4,2,'device-disabled',NULL)");
$link=$db->prepare(worker_invite_lookup_sql());
$lookup=static function($q,$token){$q->execute([$token]);return $q->fetch(PDO::FETCH_ASSOC);};
check((bool)$lookup($link,'permanent'),'permanent link works');
check((bool)$lookup($link,'permanent'),'permanent link remains reusable');
check((bool)$lookup($link,'permanent-used'),'converted used link is reusable');
check((bool)$lookup($link,'legacy-valid'),'valid legacy link works');
foreach(['legacy-used','legacy-expired','disabled','admin','missing'] as $token) check(!$lookup($link,$token),"invalid or revoked link rejected: $token");
$device=$db->prepare(worker_device_lookup_sql());
check((bool)$lookup($device,'device-permanent'),'permanent device valid');
check((bool)$lookup($device,'device-valid'),'unexpired legacy device valid');
check(!$lookup($device,'device-expired') && !$lookup($device,'device-disabled'),'expired or disabled device denied');
$session=$db->prepare(worker_session_device_sql()); $session->execute([1,'device-permanent']); check((bool)$session->fetch(),'connected session valid');
// Stop access removes links and devices; the next request checks the database again.
$db->exec('DELETE FROM worker_invites WHERE user_id=1; DELETE FROM worker_devices WHERE user_id=1');
check(!$lookup($link,'permanent') && !$lookup($device,'device-permanent'),'stop immediately invalidates link and device');
$session->execute([1,'device-permanent']); check(!$session->fetch(),'stop invalidates existing session on next request');
$db->exec("INSERT INTO worker_invites VALUES (8,1,'replacement',NULL,NULL)");
check((bool)$lookup($link,'replacement') && !$lookup($link,'permanent'),'replacement does not revive old link');
echo "Worker access link and device checks passed.\n";
