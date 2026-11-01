<?php
// SQLite translation tests the migration policy; still run the real migration against MySQL before rollout.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$db=new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
@$db->sqliteCreateFunction('NOW', fn()=>'2026-11-01 00:00:00');
$db->exec("CREATE TABLE app_settings(setting_key TEXT PRIMARY KEY,setting_value TEXT); CREATE TABLE worker_invites(id INTEGER PRIMARY KEY,user_id INTEGER,expires_at TEXT,used_at TEXT); CREATE TABLE worker_devices(id INTEGER PRIMARY KEY,user_id INTEGER,expires_at TEXT)");
$db->exec("INSERT INTO worker_invites VALUES (1,1,'2026-11-07',NULL),(2,2,'2026-10-01','2026-09-29'),(3,3,'2026-11-07','2026-10-30'),(4,4,'2026-10-30',NULL),(5,5,'2026-10-30','2026-10-25'),(6,5,'2026-10-30',NULL)");
$db->exec("INSERT INTO worker_devices VALUES (1,2,'2026-12-01'),(2,5,'2026-12-01'),(3,6,'2026-10-01')");
$migrate=function() use ($db) {
    $db->exec("UPDATE worker_invites AS i SET expires_at=NULL WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key='migration_003_permanent_worker_links') AND i.expires_at IS NOT NULL AND i.id IN (SELECT MAX(id) FROM worker_invites GROUP BY user_id) AND ((i.used_at IS NULL AND i.expires_at>NOW()) OR (i.used_at IS NOT NULL AND EXISTS (SELECT 1 FROM worker_devices d WHERE d.user_id=i.user_id AND d.expires_at>NOW())))");
    $db->exec("UPDATE worker_devices SET expires_at=NULL WHERE expires_at>NOW() AND NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key='migration_003_permanent_worker_links')");
    $db->exec("INSERT OR IGNORE INTO app_settings VALUES ('migration_003_permanent_worker_links','applied')");
};
function value(PDO $db,string $table,int $id) { $q=$db->prepare("SELECT expires_at FROM $table WHERE id=?"); $q->execute([$id]); return $q->fetchColumn(); }
function check(bool $ok,string $description): void { if (!$ok) {fwrite(STDERR,"FAIL: $description\n");exit(1);} }
$migrate();
check(value($db,'worker_invites',1)===null,'valid unused latest link preserved');
check(value($db,'worker_invites',2)===null,'used latest link with active device preserved');
check(value($db,'worker_invites',3)!==null,'used link without device is not revived');
check(value($db,'worker_invites',4)!==null,'expired unused link is not revived');
check(value($db,'worker_invites',5)!==null && value($db,'worker_invites',6)!==null,'older link not revived when latest is expired');
check(value($db,'worker_devices',1)===null && value($db,'worker_devices',3)!==null,'only active devices become permanent');
$db->exec("INSERT INTO worker_invites VALUES (7,7,'2026-11-20',NULL); INSERT INTO worker_devices VALUES (4,7,'2026-12-01')");
$migrate();
check(value($db,'worker_invites',7)!==null && value($db,'worker_devices',4)!==null,'migration marker prevents reapplying policy');
echo "Permanent access migration policy checks passed.\n";
