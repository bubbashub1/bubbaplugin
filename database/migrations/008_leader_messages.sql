-- Leader and family messaging storage
-- Apply manually to the intended Bubba Hub database after taking a backup.
-- The API checks readiness and never runs DDL during a request.

CREATE TABLE IF NOT EXISTS bh_leader_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organiser_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  category VARCHAR(30) NOT NULL DEFAULT 'help',
  reply TEXT NULL,
  replied_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leader_messages_organiser (organiser_id, created_at),
  KEY idx_leader_messages_sender (sender_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
