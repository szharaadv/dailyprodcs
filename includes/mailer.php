<?php
/**
 * Thin email helper. Sends via PHPMailer+SMTP when it's installed and enabled
 * in config/mail.php; otherwise falls back to appending the message to
 * logs/mail_outbox.log so the app never breaks just because email isn't set up
 * yet. Returns true only when a message was actually handed to SMTP.
 */

function _mail_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/../config/mail.php';
        $cfg = is_file($path) ? (require $path) : [];
    }
    return $cfg;
}

/** Try hard to make the PHPMailer class available. Returns true if loaded. */
function _load_phpmailer(): bool
{
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) return true;

    $candidates = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            // Manual (non-composer) drop-in needs its siblings too.
            $dir = dirname($file);
            foreach (['SMTP.php', 'Exception.php'] as $extra) {
                if (is_file($dir . '/' . $extra)) require_once $dir . '/' . $extra;
            }
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) return true;
        }
    }
    return false;
}

function _mail_log(string $to, string $subject, string $body, string $note): void
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $line = sprintf(
        "[%s] (%s) to=%s | %s\n%s\n%s\n",
        date('Y-m-d H:i:s'), $note, $to, $subject, $body,
        str_repeat('-', 60)
    );
    @file_put_contents($dir . '/mail_outbox.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Send an email to a specific address. Never throws — logs and returns false
 * on any problem (including email being disabled or PHPMailer not installed).
 */
function send_notification(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
{
    $cfg = _mail_config();
    $toEmail = trim($toEmail);
    if ($toEmail === '') { return false; }

    if (empty($cfg['enabled']) || !_load_phpmailer()) {
        _mail_log($toEmail, $subject, $textBody ?: strip_tags($htmlBody), empty($cfg['enabled']) ? 'disabled' : 'phpmailer-missing');
        return false;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['host'] ?? '';
        $mail->Port       = (int)($cfg['port'] ?? 587);
        $mail->SMTPAuth   = ($cfg['username'] ?? '') !== '';
        $mail->Username   = $cfg['username'] ?? '';
        $mail->Password   = $cfg['password'] ?? '';
        if (!empty($cfg['secure'])) $mail->SMTPSecure = $cfg['secure'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($cfg['from_email'] ?? 'no-reply@localhost', $cfg['from_name'] ?? 'Checksheet');
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Throwable $e) {
        _mail_log($toEmail, $subject, ($textBody ?: strip_tags($htmlBody)) . "\nSMTP error: " . $e->getMessage(), 'send-failed');
        return false;
    }
}

/**
 * Send a notification email to the configured Admin address.
 * Never throws — logs and returns false on any problem.
 */
function send_admin_notification(string $subject, string $htmlBody, string $textBody = ''): bool
{
    $cfg = _mail_config();
    $to = $cfg['admin_email'] ?? '';
    if ($to === '') { _mail_log('(none)', $subject, $textBody ?: strip_tags($htmlBody), 'no-admin-email'); return false; }
    return send_notification($to, $cfg['admin_name'] ?? '', $subject, $htmlBody, $textBody);
}
