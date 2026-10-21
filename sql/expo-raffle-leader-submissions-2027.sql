-- Apply to STAGING ONLY after backing up the staging database.
CREATE TABLE IF NOT EXISTS bh_expo_raffle_submissions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(80) NOT NULL DEFAULT 'bubba-me-2027',
 user_id BIGINT UNSIGNED NOT NULL,
 organiser_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(160) NOT NULL,
 description TEXT NULL,
 image_url VARCHAR(500) NULL,
 prize_value VARCHAR(60) NULL,
 redemption_terms TEXT NULL,
 fulfilment_contact VARCHAR(190) NULL,
 public_donor_consent TINYINT(1) NOT NULL DEFAULT 0,
 status ENUM('pending','changes_requested','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending',
 reviewed_by BIGINT UNSIGNED NULL,
 review_note TEXT NULL,
 reviewed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY event_status (event_key,status,created_at),
 KEY leader_submissions (user_id,organiser_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
