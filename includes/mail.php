<?php
declare(strict_types=1);
function send_report_email(int $id, array $user, string $type, array $photos, ?string $aiJson): string {
    $to = envv('MAIL_TO'); $from = envv('MAIL_FROM');
    if (!$to || !$from) return 'not_configured';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) return 'failed';
    $ai = $aiJson ? json_decode($aiJson, true) : null;
    $text = "A new field report has been submitted.\n\nWorker: {$user['name']}\nActivity: " . ucfirst($type) . "\nReport: #{$id}\nDate: " . date('Y-m-d H:i') . "\n\n";
    if (is_array($ai)) {
        $text .= "AI suggestions (must be verified):\nSummary: " . ($ai['summary'] ?? 'Not available') . "\nMerchant: " . ($ai['merchant'] ?? 'Not available') . "\nAmount: " . (isset($ai['amount']) ? $ai['amount'] . ' ' . ($ai['currency'] ?? '') : 'Not available') . "\nImage quality: " . ($ai['image_quality'] ?? 'Unknown') . "\nUncertainty: " . ($ai['uncertainty'] ?? 'None stated') . "\n\n";
    } else $text .= "AI analysis unavailable; review the original images.\n\n";
    $text .= 'Review (administrator login required): ' . app_url('report.php?id=' . $id);
    $boundary = 'gt_' . bin2hex(random_bytes(12));
    $headers = "From: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"";
    $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text));
    foreach ($photos as $i => $photo) {
        $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$photo['mime']];
        $body .= "--{$boundary}\r\nContent-Type: {$photo['mime']}; name=\"photo-" . ($i+1) . ".{$ext}\"\r\nContent-Disposition: attachment; filename=\"photo-" . ($i+1) . ".{$ext}\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode(file_get_contents($photo['path'])));
    }
    $body .= "--{$boundary}--\r\n";
    return @mail($to, 'Global Trading: new ' . $type . ' report #' . $id, $body, $headers) ? 'sent' : 'failed';
}
