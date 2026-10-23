-- Apply to staging after confirming existing table names and prefix.
-- Unique event/email constraint makes repeat scans and concurrent claims safe.
CREATE TABLE IF NOT EXISTS bh_expo_giveaway_claims (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_id BIGINT UNSIGNED NOT NULL,
 email_key VARCHAR(254) NOT NULL,
 claimed_by_admin_id BIGINT UNSIGNED NOT NULL,
 claimed_at DATETIME NOT NULL,
 UNIQUE KEY uniq_event_email (event_id,email_key),
 KEY idx_claimed_at (claimed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
