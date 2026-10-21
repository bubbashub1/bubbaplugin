-- STAGING ONLY: run after backup before enabling decision API.
CREATE TABLE IF NOT EXISTS bh_sheets_sync_review_audit (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 review_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 field_name VARCHAR(32) NOT NULL,
 decision ENUM('approved','rejected','stale') NOT NULL,
 reviewer_username VARCHAR(190) NOT NULL,
 website_fingerprint CHAR(64) NOT NULL,
 sheet_fingerprint CHAR(64) NOT NULL,
 decided_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_review (review_id),
 KEY idx_activity (activity_id,decided_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
