<?php
declare(strict_types=1);
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/** Get the administrator-controlled destination, never a value from a worker request. */
function notification_email(): ?string {
    try {
        $q = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key=?');
        $q->execute(['notification_email']);
        $value = $q->fetchColumn();
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    } catch (PDOException $e) {
        // Existing installations need to run the settings migration first.
        return null;
    }
}

/** Build an email-client-friendly HTML summary and a plain-text fallback. */
function report_email_content(int $id, array $user, string $type, ?string $aiJson, array $outcome): array {
    $ai=$aiJson ? json_decode($aiJson,true) : null;
    if (!is_array($ai)) $ai=[];
    $status=(string)($outcome['status'] ?? 'pending');
    $approved=$status === 'approved';
    $worker=(string)($user['name'] ?? 'Unknown');
    $client=trim((string)($outcome['client'] ?? '')) ?: 'Not assigned';
    $summary=trim((string)($ai['summary'] ?? '')) ?: 'AI analysis is unavailable. Please review the original photos.';
    $merchant=trim((string)($ai['merchant'] ?? '')) ?: 'Not identified';
    $category=trim((string)($outcome['category'] ?? '')) ?: (trim((string)($ai['category'] ?? '')) ?: 'Not identified');
    if ($type !== 'expense') { $amount='Not applicable'; $amountLabel='AMOUNT'; }
    elseif ($approved && ($outcome['amount'] ?? null) !== null) { $amount='AED '.number_format((float)$outcome['amount'],2); $amountLabel='COUNTED TOTAL'; }
    elseif (isset($ai['amount']) && is_numeric($ai['amount'])) { $currency=trim((string)($ai['currency'] ?? '')); $amount=($currency !== '' ? $currency.' ' : '').number_format((float)$ai['amount'],2); $amountLabel='AI ESTIMATE · NOT COUNTED'; }
    else { $amount='Not available'; $amountLabel='TOTAL · NOT COUNTED'; }
    $statusLabel=$approved ? (($outcome['last_decision'] ?? '') === 'AI auto-approved' ? 'AI approved · counted' : 'Approved · counted') : ($status === 'rejected' ? 'Rejected · not counted' : 'Needs review · not counted');
    $reviewNote='';
    if (!$approved && $status === 'pending') {
        if ($type === 'expense' && (int)($outcome['photo_count'] ?? 0) > 1) $reviewNote='Multiple expense photos need a manual total check.';
        elseif (($outcome['ai_status'] ?? '') !== 'completed') $reviewNote='AI analysis was unavailable. Please review the photos.';
        else $reviewNote='This report needs an administrator review before it is counted.';
    }
    $action=$approved ? 'review' : 'approve';
    $button=$approved ? 'Calculation is wrong' : 'Review and approve';
    $actionUrl=app_url('email_action.php?id='.$id.'&action='.$action);
    $reportUrl=app_url('email_action.php?id='.$id.'&action=view');
    $esc=static fn(string $v): string => htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $box=static function(string $label,string $value) use ($esc): string {
        return '<td width="33%" style="padding:5px;vertical-align:top"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f7fd;border:1px solid #e1ecf8;border-radius:10px"><tr><td style="padding:13px 10px"><div style="font-size:9px;letter-spacing:.6px;font-weight:bold;color:#8197ae">'.$esc($label).'</div><div style="font-size:14px;line-height:20px;font-weight:bold;color:#173e70;padding-top:6px;word-break:break-word">'.$esc($value).'</div></td></tr></table></td>';
    };
    $html='<!doctype html><html lang="en"><body style="margin:0;padding:22px 10px;background:#f2f6fb;font-family:Arial,sans-serif;color:#203650"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border:1px solid #e6edf5;border-radius:16px"><tr><td style="padding:30px 28px">'
      .'<div style="font-size:11px;letter-spacing:2px;color:#3976b5;font-weight:bold">GLOBAL TRADING · FIELD REPORTS</div><h1 style="font-size:23px;line-height:1.3;color:#102d52;margin:15px 0 6px">New '. $esc(ucfirst($type)).' report</h1><p style="margin:0 0 22px;color:#8294a8;font-size:13px">Report #'.$id.' · '. $esc(date('d M Y, H:i')).'</p>'
      .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafd;border-radius:10px"><tr><td style="padding:15px;font-size:13px;line-height:1.8;color:#566d84"><strong style="color:#183e70">Client:</strong> '.$esc($client).'<br><strong style="color:#183e70">Worker:</strong> '.$esc($worker).'<br><strong style="color:#183e70">Merchant:</strong> '.$esc($merchant).'<br><strong style="color:#183e70">Expense category:</strong> '.$esc($category).'</td></tr></table>'
      .'<div style="padding:20px 0 8px;font-size:10px;letter-spacing:1px;font-weight:bold;color:#8297ae">AI SUMMARY</div><p style="font-size:14px;line-height:1.65;color:#506b87;margin:0 0 18px">'.$esc($summary).'</p>'
      .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>'.$box($amountLabel,$amount).$box('ACTIVITY',ucfirst($type)).$box('STATUS',$statusLabel).'</tr></table>'
      .($reviewNote !== '' ? '<p style="margin:14px 0 0;padding:12px;background:#fff7e8;color:#926d2b;border-radius:8px;font-size:12px">'.$esc($reviewNote).'</p>' : '')
      .'<p style="margin:18px 0;color:#758da5;font-size:12px;line-height:1.6">Original photos are attached. AI can make mistakes; review the image and correct any errors.</p>'
      .'<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="padding:0 8px 8px 0"><a href="'.$esc($actionUrl).'" style="display:inline-block;padding:13px 17px;background:#1757a5;color:#ffffff;font-size:13px;font-weight:bold;text-decoration:none;border-radius:8px">'.$esc($button).' →</a></td><td style="padding-bottom:8px"><a href="'.$esc($reportUrl).'" style="display:inline-block;padding:12px 15px;border:1px solid #d3e1f0;color:#35689d;font-size:13px;font-weight:bold;text-decoration:none;border-radius:8px">Open report</a></td></tr></table>'
      .'<p style="font-size:11px;line-height:1.5;color:#9ba9b9;margin:12px 0 0">Actions require administrator sign-in and confirmation. No changes happen by opening this email.</p></td></tr></table></td></tr></table></body></html>';
    $text="Global Trading — new ".ucfirst($type)." report #{$id}\nClient: {$client}\nWorker: {$worker}\nMerchant: {$merchant}\nExpense category: {$category}\n\nAI summary: {$summary}\n{$amountLabel}: {$amount}\nActivity: ".ucfirst($type)."\nStatus: {$statusLabel}\n{$reviewNote}\n\n{$button}: {$actionUrl}\nOpen report: {$reportUrl}\nOriginal photos are attached. Administrator sign-in and confirmation are required.";
    return [$html,$text];
}

function send_report_email(int $id, array $user, string $type, array $photos, ?string $aiJson, array $outcome): string {
    $to = notification_email();
    $host = envv('SMTP_HOST'); $from = envv('MAIL_FROM');
    $username = envv('SMTP_USERNAME'); $password = envv('SMTP_PASSWORD');
    $port = filter_var(envv('SMTP_PORT', '587'), FILTER_VALIDATE_INT);
    $encryption = strtolower(envv('SMTP_ENCRYPTION', 'tls'));
    if (!$to || !$host || !$from) return 'not_configured';
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !$port || $port < 1 || $port > 65535 || !in_array($encryption, ['tls','ssl','none'], true) || (($username === '') !== ($password === ''))) return 'failed';
    [$html,$text]=report_email_content($id,$user,$type,$aiJson,$outcome);
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP(); $mail->Host = $host; $mail->Port = $port;
        $mail->SMTPAuth = $username !== ''; $mail->Username = $username; $mail->Password = $password;
        $mail->SMTPSecure = $encryption === 'tls' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : ($encryption === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : '');
        $mail->SMTPAutoTLS = $encryption !== 'none';
        $mail->Timeout = 20; $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, envv('MAIL_FROM_NAME', 'Global Trading'));
        $mail->addAddress($to); $mail->Subject = 'Global Trading: new ' . $type . ' report #' . $id;
        $mail->isHTML(true); $mail->Body = $html; $mail->AltBody = $text;
        $exts = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        foreach ($photos as $i => $photo) {
            if (!isset($exts[$photo['mime']]) || !is_file($photo['path'])) throw new RuntimeException('Photo attachment unavailable.');
            $mail->addAttachment($photo['path'], 'photo-' . ($i + 1) . '.' . $exts[$photo['mime']]);
        }
        return $mail->send() ? 'sent' : 'failed';
    } catch (Throwable $e) {
        error_log('Global Trading: SMTP notification failed for report ' . $id);
        return 'failed';
    }
}
