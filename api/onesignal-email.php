<?php
declare(strict_types=1);

/**
 * Backwards-compatible transactional email wrapper.
 *
 * Existing callers can continue using bh_send_onesignal_email(), but all
 * transactional email now goes through Bubba Hub's authenticated SMTP
 * mailbox: newsletter@bubbahub.co.uk.
 */
function bh_send_onesignal_email(string $email, string $subject, string $html, string $plainText=''): bool {
    require_once __DIR__.'/mailer.php';
    return bh_send_smtp_mail($email, $subject, $html, $plainText);
}
?>