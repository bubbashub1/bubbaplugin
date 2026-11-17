-- Bubba Hub consolidated inbox and support migration (replaces PRs #795, #797 and #803).
-- One canonical leader-message table is shared by public enquiries, leader inbox and support routing.

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
 PRIMARY KEY(id),
 INDEX idx_leader_messages_organiser(organiser_id,created_at),
 INDEX idx_leader_messages_sender(sender_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_leader_messages' AND COLUMN_NAME='sender_email')=0,'ALTER TABLE bh_leader_messages ADD COLUMN sender_email VARCHAR(190) NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_leader_messages' AND INDEX_NAME='idx_leader_messages_sender')=0,'ALTER TABLE bh_leader_messages ADD KEY idx_leader_messages_sender(sender_user_id)','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;

CREATE TABLE IF NOT EXISTS bh_guidance_topics (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(120) NOT NULL,
 description VARCHAR(500) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_guidance_topics_name(name)
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
 PRIMARY KEY(id),
 INDEX idx_support_user(user_id),
 INDEX idx_support_assigned(assigned_user_id),
 INDEX idx_support_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_support_answers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 question_id BIGINT UNSIGNED NOT NULL,
 responder_user_id BIGINT UNSIGNED NOT NULL,
 answer TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 INDEX idx_support_answer_question(question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
