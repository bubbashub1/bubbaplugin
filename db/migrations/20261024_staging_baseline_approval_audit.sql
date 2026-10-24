-- STAGING ONLY. Requires explicit DBA review and staging backup.
-- Approval endpoint fails closed until this table exists.
CREATE TABLE IF NOT EXISTS bh_sheets_sync_baseline_approval_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  baseline_id BIGINT UNSIGNED NOT NULL,
  review_id BIGINT UNSIGNED NOT NULL,
  activity_id BIGINT UNSIGNED NOT NULL,
  admin_id BIGINT UNSIGNED NOT NULL,
  admin_username VARCHAR(190) NOT NULL,
  website_fingerprint CHAR(64) NOT NULL,
  sheet_fingerprint CHAR(64) NOT NULL,
  approved_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_baseline_approval (baseline_id),
  UNIQUE KEY uniq_review_approval (review_id),
  KEY idx_activity_approval (activity_id, approved_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
