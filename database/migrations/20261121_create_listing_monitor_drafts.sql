-- Listing Monitor: saved review drafts for activity changes.
-- Safe additive migration. Does not modify bh_activities or publish any proposed values.
CREATE TABLE IF NOT EXISTS bh_listing_monitor_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  finding_id BIGINT UNSIGNED NULL,
  activity_id BIGINT UNSIGNED NOT NULL,
  status ENUM('draft','ready_for_review','approved','rejected') NOT NULL DEFAULT 'draft',
  original_snapshot MEDIUMTEXT NOT NULL,
  proposed_changes MEDIUMTEXT NOT NULL,
  source_url VARCHAR(1000) NULL,
  source_evidence MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  reviewed_by VARCHAR(190) NULL,
  KEY idx_monitor_draft_activity (activity_id,status),
  KEY idx_monitor_draft_finding (finding_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable approval history for explicitly approved monitor updates.
CREATE TABLE IF NOT EXISTS bh_listing_monitor_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  draft_id BIGINT UNSIGNED NOT NULL,
  activity_id BIGINT UNSIGNED NOT NULL,
  admin_username VARCHAR(190) NOT NULL,
  before_values MEDIUMTEXT NOT NULL,
  approved_values MEDIUMTEXT NOT NULL,
  source_url VARCHAR(1000) NULL,
  approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_monitor_audit_activity (activity_id,approved_at),
  KEY idx_monitor_audit_draft (draft_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
