-- Bubba Hub Stage 5c: staging-only approved sync baselines.
-- REVIEW BEFORE RUNNING. DO NOT APPLY TO LIVE. No existing table is modified.
-- InnoDB, immutable history by convention; enforce writes via future admin-only endpoint.
CREATE TABLE IF NOT EXISTS bh_sheets_sync_baselines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  environment VARCHAR(16) NOT NULL,
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
  UNIQUE KEY uniq_environment_activity_version (environment, activity_id, version),
  UNIQUE KEY uniq_approval_audit_id (approval_audit_id),
  KEY idx_activity_latest (environment, activity_id, approved_at_utc),
  CONSTRAINT chk_sync_baseline_staging CHECK (environment = 'staging'),
  CONSTRAINT chk_sync_baseline_json CHECK (JSON_VALID(field_values_json)),
  CONSTRAINT chk_sync_baseline_version CHECK (version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
