<?php
declare(strict_types=1);

/**
 * Bubba Hub SMTP mailer.
 *
 * Credentials are read from server-only BH_SMTP_* constants in wp-config.php.
 * Never store the mailbox password in this repository.
 */
function bh_send_smtp_mail(
    string $to,
    string $subject,
    string $html,
    string $plainText = '',
    string $replyTo = ''
): bool {
    $to = strtolower(trim($to));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $wpConfig = dirname(__DIR__) . '/wp-config.php';
    if (!is_file($wpConfig)) return false;
    require_once $wpConfig;

    $host = defined('BH_SMTP_HOST') ? (string)BH_SMTP_HOST : 'smtp.bubbahub.co.uk';
    $port = defined('BH_SMTP_PORT') ? (int)BH_SMTP_PORT : 465;
    $username = defined('BH_SMTP_USERNAME') ? (string)BH_SMTP_USERNAME : 'newsletter@bubbahub.co.uk';
    $password = defined('BH_SMTP_PASSWORD') ? (string)BH_SMTP_PASSWORD : '';
    $secure = defined('BH_SMTP_SECURE') ? strtolower((string)BH_SMTP_SECURE) : 'ssl';
    $from = defined('BH_SMTP_FROM') ? (string)BH_SMTP_FROM : 'newsletter@bubbahub.co.uk';
    $fromName = defined('BH_SMTP_FROM_NAME') ? (string)BH_SMTP_FROM_NAME : 'Bubba Hub';

    if ($password === '' || $password === 'YOUR-NEWSLETTER-MAILBOX-PASSWORD') {
        error_log('Bubba Hub SMTP: BH_SMTP_PASSWORD is not configured.');
        return false;
    }

    $phpMailerDir = dirname(__DIR__) . '/wp-includes/PHPMailer';
    foreach (['Exception.php','PHPMailer.php','SMTP.php'] as $file) {
        $path = $phpMailerDir . '/' . $file;
        if (!is_file($path)) {
            error_log('Bubba Hub SMTP: WordPress PHPMailer file missing: ' . $path);
            return false;
        }
        require_once $path;
    }

    try {
        $mail = new PHPMailerPHPMailerPHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = ($secure === 'tls' || $secure === 'starttls')
            ? PHPMailerPHPMailerPHPMailer::ENCRYPTION_STARTTLS
            : PHPMailerPHPMailerPHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $plainText !== '' ? $plainText : trim(strip_tags($html));
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Bubba Hub SMTP mail failed: ' . $e->getMessage());
        return false;
    }
}
