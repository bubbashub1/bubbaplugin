<?php
declare(strict_types=1);

/**
 * Bubba Hub SMTP mailer.
 *
 * All outgoing mail uses noreply@bubbahub.co.uk.
 * Passwords remain server-only in wp-config.php.
 */
function bh_send_smtp_mail(
    string $to,
    string $subject,
    string $html,
    string $plainText = '',
    string $replyTo = '',
    string $mailbox = 'transactional'
): bool {
    $to = strtolower(trim($to));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $wpConfig = dirname(__DIR__) . '/wp-config.php';
    if (!is_file($wpConfig)) return false;
    require_once $wpConfig;

    $host = defined('BH_SMTP_HOST') ? (string)BH_SMTP_HOST : 'smtp.bubbahub.co.uk';
    $port = defined('BH_SMTP_PORT') ? (int)BH_SMTP_PORT : 465;
    $secure = defined('BH_SMTP_SECURE') ? strtolower((string)BH_SMTP_SECURE) : 'ssl';

    // All Bubba Hub outgoing email uses the configured noreply mailbox.
    $username = defined('BH_SMTP_USERNAME') ? (string)BH_SMTP_USERNAME : 'noreply@bubbahub.co.uk';
    $password = defined('BH_SMTP_PASSWORD') ? (string)BH_SMTP_PASSWORD : '';
    $from = defined('BH_SMTP_FROM') ? (string)BH_SMTP_FROM : 'noreply@bubbahub.co.uk';
    $fromName = defined('BH_SMTP_FROM_NAME') ? (string)BH_SMTP_FROM_NAME : 'Bubba Hub';
    $passwordPlaceholder = 'YOUR-NOREPLY-MAILBOX-PASSWORD';

    if ($password === '' || $password === $passwordPlaceholder) {
        error_log('Bubba Hub SMTP: mailbox password is not configured for ' . $username . '.');
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
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = ($secure === 'tls' || $secure === 'starttls')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
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
        error_log('Bubba Hub SMTP mail failed [' . $username . ']: ' . $e->getMessage());
        return false;
    }
}
