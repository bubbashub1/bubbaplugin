<?php
declare(strict_types=1);
require_once __DIR__ . '/welcome-email-copy.php';

/**
 * At-most-once welcome delivery. Reserve before sending to avoid duplicates.
 * An interrupted send requires manual review; never retry automatically.
 */
function bh_leader_welcome_after_verification(PDO $db, int $userId): void {
    try {
        if ($userId < 1) return;
        $db->exec("CREATE TABLE IF NOT EXISTS bh_welcome_mail_log (
            user_id BIGINT UNSIGNED NOT NULL,
            mail_type VARCHAR(32) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, mail_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $q = $db->prepare("SELECT email FROM bh_users WHERE id=? AND role='leader' AND email_verified_at IS NOT NULL LIMIT 1");
        $q->execute([$userId]);
        $email = $q->fetchColumn();
        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;
        $claim = $db->prepare("INSERT IGNORE INTO bh_welcome_mail_log (user_id,mail_type) VALUES (?,'leader_welcome')");
        $claim->execute([$userId]);
        if ($claim->rowCount() !== 1) return;
        require_once __DIR__ . '/mailer.php';
        $message = bh_welcome_email_copy('leader');
        if (!bh_send_smtp_mail($email, $message['subject'], $message['html'], $message['plain'])) {
            error_log('Bubba Hub leader welcome could not be delivered for user id '.$userId);
        }
    } catch (Throwable $e) {
        error_log('Bubba Hub leader welcome error: '.$e->getMessage());
    }
}
