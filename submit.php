<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/ai.php';
require __DIR__ . '/includes/mail.php';
$user = require_role('worker');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('worker.php');
check_csrf();
$type = (string)($_POST['type'] ?? '');
if (!in_array($type, ['expense','installation','delivery','receiving'], true)) { flash('Please choose an activity.', 'error'); redirect('worker.php'); }
$files = $_FILES['photos'] ?? null;
if (!$files || !is_array($files['name'])) { flash('Please choose between 1 and 5 photos.', 'error'); redirect('worker.php'); }
$photos = []; $hashes = []; $dir = __DIR__ . '/storage/uploads/';
try {
    foreach ($files['name'] as $i => $_) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if (count($photos) >= 5) throw new RuntimeException('Please choose no more than 5 photos.');
        if ($files['error'][$i] !== UPLOAD_ERR_OK || $files['size'][$i] > 5*1024*1024 || $files['size'][$i] < 1) throw new RuntimeException('A photo could not be uploaded. Each photo must be less than 5 MB.');
        $tmp = $files['tmp_name'][$i];
        if (!is_uploaded_file($tmp)) throw new RuntimeException('Invalid upload.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp); $exts = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        $imageInfo = @getimagesize($tmp);
        if (!isset($exts[$mime]) || !$imageInfo || ($imageInfo['mime'] ?? '') !== $mime) throw new RuntimeException('Only valid JPG, PNG and WEBP images are allowed.');
        $hash = hash_file('sha256', $tmp);
        if (in_array($hash, $hashes, true)) throw new RuntimeException('The same photo was selected more than once.');
        if ($type === 'expense') {
            $q = db()->prepare("SELECT p.report_id FROM report_photos p JOIN reports r ON r.id=p.report_id WHERE p.sha256=? AND r.type='expense' LIMIT 1"); $q->execute([$hash]);
            if ($q->fetchColumn()) throw new RuntimeException('This exact receipt photo was already submitted. Please contact management if this is a mistake.');
        }
        $hashes[] = $hash;
        $filename = bin2hex(random_bytes(20)) . '.' . $exts[$mime]; $path = $dir . $filename;
        if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('Could not save the photo. Contact management.');
        $photos[] = ['filename'=>$filename, 'path'=>$path, 'mime'=>$mime, 'hash'=>$hash];
    }
    if (!$photos) throw new RuntimeException('Please add at least one photo.');
    $pdo = db(); $pdo->beginTransaction();
    $q = $pdo->prepare('INSERT INTO reports (user_id,type) VALUES (?,?)'); $q->execute([$user['id'],$type]); $id = (int)$pdo->lastInsertId();
    $q = $pdo->prepare('INSERT INTO report_photos (report_id,filename,mime,sha256) VALUES (?,?,?,?)');
    foreach ($photos as $photo) $q->execute([$id,$photo['filename'],$photo['mime'],$photo['hash']]);
    audit((int)$user['id'], $id, 'submitted', 'New ' . $type . ' report'); $pdo->commit();
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    foreach ($photos as $p) @unlink($p['path']);
    flash($ex instanceof RuntimeException ? $ex->getMessage() : 'Could not save the report. Please try again.', 'error'); redirect('worker.php');
}
// The report is committed before external services are called: their failure cannot lose a submission.
if (function_exists('set_time_limit')) @set_time_limit(120);
try { [$aiStatus, $aiData, $aiError] = analyze_report($type, $photos); } catch (Throwable $ex) { $aiStatus='failed'; $aiData=null; $aiError='Analysis failed unexpectedly.'; }
$decision = auto_approval_decision($type, $aiStatus, $aiData, count($photos));
// Serialize with management actions and never override an administrator's decision.
try {
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT status,amount,category,notes,ai_status FROM reports WHERE id=? FOR UPDATE'); $q->execute([$id]); $current=$q->fetch();
    if (!$current) throw new RuntimeException('Report not found.');
    $q=$pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE report_id=? AND action='review updated'"); $q->execute([$id]); $wasReviewed=(int)$q->fetchColumn()>0;
    if ($current['ai_status']==='not_configured') {
        $q=$pdo->prepare('UPDATE reports SET ai_status=?,ai_data=?,ai_error=? WHERE id=?'); $q->execute([$aiStatus,$aiData,$aiError,$id]);
        if ($decision && $current['status']==='pending' && !$wasReviewed) {
            $aiResult = json_decode((string)$aiData,true);
            $summary = is_array($aiResult) && is_string($aiResult['summary'] ?? null) ? clean_ai_text($aiResult['summary'],500) : '';
            $notes = trim((string)($current['notes'] ?? '')) !== '' ? $current['notes'] : ($summary !== '' ? $summary : null);
            $q=$pdo->prepare("UPDATE reports SET status='approved',amount=?,category=?,notes=? WHERE id=?"); $q->execute([$decision['amount'],$decision['category'],$notes,$id]);
            audit((int)$user['id'],$id,'AI auto-approved',json_encode(['source'=>'Gemini','amount'=>$decision['amount'],'category'=>$decision['category'],'notes'=>$notes],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
        }
    }
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Could not store analysis for report ' . $id);
}
try {
    // Read the committed outcome: the email must never claim approval that was not saved.
    $q=db()->prepare('SELECT r.status,r.amount,r.category,r.ai_data,r.ai_status,p.client,(SELECT a.action FROM audit_log a WHERE a.report_id=r.id AND a.action IN (\'AI auto-approved\',\'review updated\') ORDER BY a.id DESC LIMIT 1) last_decision FROM reports r LEFT JOIN projects p ON p.id=r.project_id WHERE r.id=?'); $q->execute([$id]); $outcome=$q->fetch();
    if ($outcome) $outcome['photo_count']=count($photos);
    $emailStatus=$outcome ? send_report_email($id,$user,$type,$photos,$outcome['ai_data'],$outcome) : 'failed';
    $q=db()->prepare('UPDATE reports SET email_status=? WHERE id=?'); $q->execute([$emailStatus,$id]);
} catch (Throwable $ex) { error_log('Could not send or record notification for report ' . $id); }
$_SESSION['worker_submission_success'] = true; flash('Report sent successfully. Thank you!'); redirect('worker.php');
