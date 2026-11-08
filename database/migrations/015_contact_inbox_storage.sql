-- Bubba Hub migration 015: public activity enquiry inbox storage.
-- Safe to run on a fresh install or an existing installation. Existing
-- bh_leader_messages tables are retained and sender_email is added only
-- when it is not already present.

CREATE TABLE IF NOT EXISTS bh_leader_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organiser_id BIGINT UNSIGNED NOT NULL,
    sender_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sender_email VARCHAR(190) NULL,
    subject VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'help',
    reply TEXT NULL,
    replied_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_leader_messages_organiser (organiser_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @bh_has_sender_email = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bh_leader_messages'
      AND COLUMN_NAME = 'sender_email'
);
SET @bh_add_sender_email = IF(
    @bh_has_sender_email = 0,
    'ALTER TABLE bh_leader_messages ADD COLUMN sender_email VARCHAR(190) NULL',
    'SELECT 1'
);
PREPARE bh_stmt FROM @bh_add_sender_email;
EXECUTE bh_stmt;
DEALLOCATE PREPARE bh_stmt;
