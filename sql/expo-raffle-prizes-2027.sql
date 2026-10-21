CREATE TABLE IF NOT EXISTS bh_expo_raffle_prizes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(80) NOT NULL DEFAULT 'bubba-me-2027',
 title VARCHAR(160) NOT NULL,
 description TEXT NULL,
 image_url VARCHAR(500) NULL,
 donor_name VARCHAR(160) NULL,
 donor_url VARCHAR(500) NULL,
 prize_value VARCHAR(60) NULL,
 entry_type ENUM('raffle','giveaway') NOT NULL DEFAULT 'raffle',
 status ENUM('draft','published','claimed') NOT NULL DEFAULT 'draft',
 sort_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY event_status_order (event_key,status,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
