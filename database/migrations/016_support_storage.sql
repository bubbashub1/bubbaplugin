-- Bubba Hub migration 016: support and leader-routing storage.
-- Includes the shared leader inbox table for installations where migration
-- 015 has not yet been applied. The table/column checks make this safe to run
-- before or after migration 015.

CREATE TABLE IF NOT EXISTS bh_guidance_topics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_guidance_topics_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_support_questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    topic_id BIGINT UNSIGNED NULL,
    subject VARCHAR(190) NULL,
    question TEXT NOT NULL,
    status ENUM('submitted','assigned','awaiting_user','answered','closed') NOT NULL DEFAULT 'submitted',
    assigned_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_support_user (user_id),
    INDEX idx_support_assigned (assigned_user_id),
    INDEX idx_support_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_support_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_id BIGINT UNSIGNED NOT NULL,
    responder_user_id BIGINT UNSIGNED NOT NULL,
    answer TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_support_answer_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @bh_has_sender_email = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
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
