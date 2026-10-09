-- Run once in phpMyAdmin, using the Bubba Hub WordPress database.
CREATE TABLE IF NOT EXISTS bh_leader_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organiser_id BIGINT UNSIGNED NOT NULL,
 sender_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 sender_email VARCHAR(190) NULL,
 subject VARCHAR(180) NOT NULL,
 body TEXT NOT NULL,
 category VARCHAR(30) NOT NULL DEFAULT 'help',
 reply TEXT NULL,
 replied_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_leader_messages_organiser (organiser_id,created_at),
 INDEX idx_leader_messages_sender (sender_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- If the table existed before sender_email was added, run the following separately
-- only if SHOW COLUMNS FROM bh_leader_messages LIKE 'sender_email' returns no rows:
-- ALTER TABLE bh_leader_messages ADD COLUMN sender_email VARCHAR(190) NULL;
