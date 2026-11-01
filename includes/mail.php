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

function send_report_email(int $id, array $user, string $type, array $photos, ?string $aiJson): string {
    $to = notification_email();
    $host = envv('SMTP_HOST'); $from = envv('MAIL_FROM');
    $username = envv('SMTP_USERNAME'); $password = envv('SMTP_PASSWORD');
    $port = filter_var(envv('SMTP_PORT', '587'), FILTER_VALIDATE_INT);
    $encryption = strtolower(envv('SMTP_ENCRYPTION', 'tls'));
    if (!$to || !$host || !$from) return 'not_configured';
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !$port || $port < 1 || $port > 65535 || !in_array($encryption, ['tls','ssl','none'], true) || (($username === '') !== ($password === ''))) return 'failed';

    $ai = $aiJson ? json_decode($aiJson, true) : null;
    $text = "A new field report has been submitted.\n\nWorker: {$user['name']}\nActivity: " . ucfirst($type) . "\nReport: #{$id}\nDate: " . date('Y-m-d H:i') . "\n\n";
    if (is_array($ai)) {
        $text .= "AI suggestions (must be verified):\nSummary: " . ($ai['summary'] ?? 'Not available') . "\nMerchant: " . ($ai['merchant'] ?? 'Not available') . "\nAmount: " . (isset($ai['amount']) ? $ai['amount'] . ' ' . ($ai['currency'] ?? '') : 'Not available') . "\nImage quality: " . ($ai['image_quality'] ?? 'Unknown') . "\nUncertainty: " . ($ai['uncertainty'] ?? 'None stated') . "\n\n";
    } else $text .= "AI analysis unavailable; review the original images.\n\n";
    $text .= 'Review (administrator login required): ' . app_url('report.php?id=' . $id);
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP(); $mail->Host = $host; $mail->Port = $port;
        $mail->SMTPAuth = $username !== ''; $mail->Username = $username; $mail->Password = $password;
        $mail->SMTPSecure = $encryption === 'tls' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : ($encryption === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : '');
        $mail->SMTPAutoTLS = $encryption !== 'none';
        $mail->Timeout = 20; $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, envv('MAIL_FROM_NAME', 'Global Trading'));
        $mail->addAddress($to);
        $mail->Subject = 'Global Trading: new ' . $type . ' report #' . $id;
        $mail->Body = $text;
        $exts = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        foreach ($photos as $i => $photo) {
            if (!isset($exts[$photo['mime']]) || !is_file($photo['path'])) throw new RuntimeException('Photo attachment unavailable.');
            $mail->addAttachment($photo['path'], 'photo-' . ($i + 1) . '.' . $exts[$photo['mime']]);
        }
        return $mail->send() ? 'sent' : 'failed';
    } catch (Throwable $e) {
        // Do not expose provider responses, credentials, or addresses in the UI/logs.
        error_log('Global Trading: SMTP notification failed for report ' . $id);
        return 'failed';
    }
}
