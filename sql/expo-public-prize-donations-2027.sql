-- Apply to staging first; back up live database before applying to live.
CREATE TABLE IF NOT EXISTS bh_expo_public_prize_donations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(80) NOT NULL,
 donor_name VARCHAR(160) NOT NULL,
 email VARCHAR(190) NOT NULL,
 title VARCHAR(160) NOT NULL,
 description TEXT NOT NULL,
 prize_value VARCHAR(60) NULL,
 donor_url VARCHAR(500) NULL,
 collection_details VARCHAR(500) NULL,
 public_credit TINYINT(1) NOT NULL DEFAULT 0,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY event_status (event_key,status),
 KEY email_created (email,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
