-- Listing Monitor: private admin review drafts for activity changes.
-- Additive only. Approved drafts retain their original snapshot, approved values,
-- reviewer and review timestamp as the immutable approval record.
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
