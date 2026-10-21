-- STAGING ONLY. Creates the moderation queue; no tickets or payments.
CREATE TABLE IF NOT EXISTS bh_expo_raffle_submissions (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 event_key VARCHAR(100) NOT NULL DEFAULT 'bubba-me-2027',
 leader_id INT UNSIGNED NULL,
 title VARCHAR(160) NOT NULL,
 description TEXT NULL,
 image_url VARCHAR(500) NULL,
 donor_name VARCHAR(160) NULL,
 donor_url VARCHAR(500) NULL,
 prize_value VARCHAR(60) NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 reviewed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_event_status(event_key,status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
