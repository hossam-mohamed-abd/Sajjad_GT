<?php
require __DIR__ . '/includes/bootstrap.php';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
if ($id < 1 || !in_array($action, ['review','approve','view'], true)) { http_response_code(400); exit('Invalid action.'); }
$user = current_user();
if (!$user) {
    $_SESSION['after_admin_login'] = 'email_action.php?id='.$id.'&action='.$action;
    redirect('index.php');
}
if ($user['role'] !== 'admin') {
    http_response_code(403);
    exit('Administrator access required. Please sign out of the worker account, then open this email link again and sign in as an administrator.');
}
if ($action === 'view') redirect('report.php?id='.$id);
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT * FROM reports WHERE id=? FOR UPDATE'); $q->execute([$id]); $current=$q->fetch();
        if (!$current) throw new RuntimeException('Report not found.');
        $status=$action === 'review' ? 'pending' : 'approved';
        $amount=$current['amount'];
        if ($action === 'approve' && $current['type'] === 'expense') {
            $amount=trim((string)($_POST['amount'] ?? ''));
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/D',$amount) || (float)$amount < 0 || (float)$amount > 9999999999) throw new RuntimeException('Enter a valid amount in AED with up to two decimal places.');
        }
        $q=$pdo->prepare('UPDATE reports SET status=?,amount=? WHERE id=?'); $q->execute([$status,$amount,$id]);
        $details=json_encode(['source'=>'email action', 'before'=>['status'=>$current['status'],'amount'=>$current['amount']], 'after'=>['status'=>$status,'amount'=>$amount]],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        audit((int)$user['id'],$id,'review updated',$details ?: 'Review updated via email action');
        $pdo->commit();
        flash($action === 'review' ? 'Report moved to review and excluded from approved spending.' : ($current['type'] === 'expense' ? 'Report approved. The amount is now included in approved spending.' : 'Report approved.'));
        redirect('report.php?id='.$id);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($e instanceof RuntimeException ? $e->getMessage() : 'Could not update the report.', 'error');
        redirect('email_action.php?id='.$id.'&action='.$action);
    }
}
$q=$pdo->prepare('SELECT r.*, u.name worker, p.client FROM reports r JOIN users u ON u.id=r.user_id LEFT JOIN projects p ON p.id=r.project_id WHERE r.id=?'); $q->execute([$id]); $report=$q->fetch();
if (!$report) { http_response_code(404); exit('Report not found.'); }
$q=$pdo->prepare('SELECT id FROM report_photos WHERE report_id=? ORDER BY id'); $q->execute([$id]); $photos=$q->fetchAll();
$ai=$report['ai_data'] ? json_decode($report['ai_data'],true) : null;
$suggested='';
if ($report['amount'] !== null) $suggested=(string)$report['amount'];
elseif (is_array($ai) && strtoupper(trim((string)($ai['currency'] ?? ''))) === 'AED' && isset($ai['amount']) && is_numeric($ai['amount']) && (float)$ai['amount'] >= 0 && (float)$ai['amount'] <= 9999999999) $suggested=number_format((float)$ai['amount'],2,'.','');
page_start($action==='review'?'Move to review':'Confirm report',$user,'dashboard');
?>
<a class="back-link" href="report.php?id=<?=$id?>">← Open full report</a>
<div class="heading-row"><div><div class="eyebrow">EMAIL ACTION / #<?=$id?></div><h1><?=$action==='review'?'Something not right?':'Confirm this report'?></h1><p>Review the current details before confirming. Opening the email does not change the report.</p></div><span class="status <?=e($report['status'])?>"><?=e($report['status'])?></span></div>
<div class="detail-layout"><section class="panel"><span class="step-label">ORIGINAL EVIDENCE</span><h2>Report photos</h2><p>Submitted by <?=e($report['worker'])?> · <?=e(ucfirst($report['type']))?></p><div class="photo-grid email-action-photos"><?php foreach($photos as $photo): ?><a href="photo.php?id=<?=$photo['id']?>" target="_blank" rel="noopener"><img src="photo.php?id=<?=$photo['id']?>" alt="Report photo"></a><?php endforeach; ?></div></section>
<aside class="panel review-panel"><span class="step-label">CONFIRM YOUR ACTION</span><h2><?=$action==='review'?'Move to review':'Approve report'?></h2><p><?=$action==='review'?'This will remove the report from approved spending until it is approved again.':'Check the photo and amount before approving.'?></p><form method="post" class="stack"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="<?=e($action)?>"><?php if($action==='approve' && $report['type']==='expense'): ?><label>Amount (AED)<input type="number" name="amount" min="0" max="9999999999" step="0.01" value="<?=e($suggested)?>" placeholder="Enter total" required></label><p class="small-muted">AI suggestions may be inaccurate. Confirm the amount against the image.</p><?php endif; ?><button class="button primary wide" type="submit"><?=$action==='review'?'Confirm: move to review':'Confirm: approve report'?> <span>→</span></button><a class="review-link" href="report.php?id=<?=$id?>">Open full report to edit details →</a></form></aside></div>
<?php page_end(); ?>
