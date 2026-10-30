CREATE TABLE IF NOT EXISTS bh_claim_invitations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 activity_id BIGINT UNSIGNED NOT NULL,
 recipient VARCHAR(190) NOT NULL,
 token_hash CHAR(64) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 sent_at DATETIME NULL,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unique_activity_recipient (activity_id, recipient),
 KEY status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bh_listing_claims (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 activity_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 organiser_id BIGINT UNSIGNED NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 message TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 KEY idx_claim_activity_status (activity_id,status),
 KEY idx_claim_user_status (user_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
