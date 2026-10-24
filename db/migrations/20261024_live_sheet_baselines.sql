-- PROPOSED production baseline storage. REVIEW AND BACK UP BEFORE APPLYING.
-- This migration is NOT automatically executed by deployment.
-- Existing bh_sheets_sync_baselines is staging-only (CHECK environment='staging').
-- Separate table avoids modifying or weakening staging constraints.
CREATE TABLE IF NOT EXISTS bh_sheets_sync_live_baselines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  environment VARCHAR(16) NOT NULL DEFAULT 'live',
  activity_id BIGINT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  approved_by_admin_id BIGINT UNSIGNED NOT NULL,
  approved_at_utc DATETIME NOT NULL,
  field_values_json LONGTEXT NOT NULL,
  website_fingerprint CHAR(64) NOT NULL,
  sheet_fingerprint CHAR(64) NOT NULL,
  approval_audit_id VARCHAR(100) NOT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_environment_activity_version (environment,activity_id,version),
  UNIQUE KEY uniq_approval_audit_id (approval_audit_id),
  KEY idx_activity_latest (environment,activity_id,approved_at_utc),
  CONSTRAINT chk_live_baseline_environment CHECK (environment='live'),
  CONSTRAINT chk_live_baseline_json CHECK (JSON_VALID(field_values_json)),
  CONSTRAINT chk_live_baseline_version CHECK (version>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
