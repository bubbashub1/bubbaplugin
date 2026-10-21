-- Bubba Hub staging-only Google Sheets review queue.
-- Run manually on STAGING after backup; never run on live.
-- Stores proposed field changes, not approvals or changes to bh_activities.
CREATE TABLE IF NOT EXISTS bh_sheets_sync_review_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  environment VARCHAR(16) NOT NULL DEFAULT 'staging',
  activity_id BIGINT UNSIGNED NOT NULL,
  field_name VARCHAR(32) NOT NULL,
  website_value TEXT NULL,
  sheet_value TEXT NULL,
  website_fingerprint CHAR(64) NOT NULL,
  sheet_fingerprint CHAR(64) NOT NULL,
  status ENUM('pending','approved','rejected','stale') NOT NULL DEFAULT 'pending',
  source_run_id VARCHAR(100) NOT NULL,
  reviewed_by_admin_id BIGINT UNSIGNED NULL,
  reviewed_at_utc DATETIME NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_run_activity_field (source_run_id,activity_id,field_name),
  KEY idx_pending (environment,status,created_at_utc),
  KEY idx_activity (environment,activity_id),
  CONSTRAINT chk_sheet_review_staging CHECK (environment='staging'),
  CONSTRAINT chk_sheet_review_field CHECK (field_name IN ('title','description','age_range','price_from','booking_url')),
  CONSTRAINT chk_sheet_review_source CHECK (CHAR_LENGTH(source_run_id)>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
