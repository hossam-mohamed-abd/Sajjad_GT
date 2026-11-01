<?php
require __DIR__ . '/includes/bootstrap.php';
$user = current_user(); if (!$user) { http_response_code(403); exit('Access denied'); }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$q = db()->prepare('SELECT p.*,r.user_id FROM report_photos p JOIN reports r ON r.id=p.report_id WHERE p.id=?'); $q->execute([$id]); $photo=$q->fetch();
if (!$photo || ($user['role'] !== 'admin' && (int)$photo['user_id'] !== (int)$user['id'])) { http_response_code(404); exit('Not found'); }
$path = __DIR__ . '/storage/uploads/' . basename($photo['filename']);
if (!is_file($path)) { http_response_code(404); exit('Not found'); }
header('Content-Type: ' . $photo['mime']); header('Content-Disposition: inline; filename="report-photo"'); header('Cache-Control: private, no-store'); header('Content-Length: ' . filesize($path)); readfile($path);
