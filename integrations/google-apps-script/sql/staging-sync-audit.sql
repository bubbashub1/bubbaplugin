-- Bubba Hub Sheets sync audit, STAGING ONLY.
-- Apply manually after verifying the staging database and taking a backup.
-- No production migrations or automatic writes are included in this PR.
CREATE TABLE IF NOT EXISTS bh_sheets_sync_change_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  activity_id BIGINT UNSIGNED NOT NULL,
  field_name VARCHAR(40) NOT NULL,
  old_value TEXT NULL,
  new_value TEXT NULL,
  action ENUM('apply','rollback') NOT NULL,
  related_audit_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_created (activity_id, created_at),
  KEY idx_related (related_audit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
