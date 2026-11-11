-- Bubba Hub Listing Checker persistence.
-- Run once against the standalone Bubba Hub database before enabling the admin controls.
-- This migration creates only bh_* tables and does not modify live listing records.

CREATE TABLE IF NOT EXISTS bh_listing_checker_settings (
  setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO bh_listing_checker_settings (setting_key, setting_value) VALUES
 ('health_enabled','1'),
 ('discovery_enabled','1'),
 ('interval_hours','4'),
 ('preferred_start_time','02:00'),
 ('window_start','00:00'),
 ('window_end','23:59'),
 ('active_days','[1,2,3,4,5,6,7]'),
 ('timezone','Europe/London'),
 ('batch_size','30'),
 ('max_runtime_seconds','240'),
 ('request_limit','30'),
 ('stale_after_days','180');

CREATE TABLE IF NOT EXISTS bh_listing_discovery_terms (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  term VARCHAR(190) NOT NULL,
  term_group VARCHAR(100) NOT NULL DEFAULT 'General',
  regions_json TEXT NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_searched_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_discovery_term_group (term, term_group),
  KEY idx_discovery_term_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_listing_check_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  check_type ENUM('health','discovery') NOT NULL,
  status ENUM('queued','running','completed','failed','paused') NOT NULL DEFAULT 'queued',
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  processed_count INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  summary_json MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_listing_check_runs_type_status (check_type,status),
  KEY idx_listing_check_runs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_listing_check_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  check_type ENUM('health','discovery') NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  target_term_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('pending','processing','checked','failed','skipped') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_check_at DATETIME NULL,
  last_checked_at DATETIME NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_listing_check_target (check_type,target_id,target_term_id),
  KEY idx_listing_check_due (check_type,status,next_check_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_listing_check_findings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  check_type ENUM('health','discovery') NOT NULL,
  activity_id BIGINT UNSIGNED NULL,
  term_id BIGINT UNSIGNED NULL,
  candidate_json MEDIUMTEXT NULL,
  finding_type VARCHAR(80) NOT NULL,
  confidence DECIMAL(5,2) NULL,
  source_url VARCHAR(1000) NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  review_status ENUM('new','correct','incorrect','follow_up','approved','rejected','resolved') NOT NULL DEFAULT 'new',
  reviewed_at DATETIME NULL,
  KEY idx_listing_findings_review (review_status,checked_at),
  KEY idx_listing_findings_activity (activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editable starter terms. Administrators can amend these in the control centre.
INSERT IGNORE INTO bh_listing_discovery_terms (term,term_group,regions_json) VALUES
 ('baby groups','Baby groups','["all"]'),
 ('baby sensory','Baby groups','["all"]'),
 ('baby massage','Baby & pregnancy','["all"]'),
 ('pregnancy classes','Baby & pregnancy','["all"]'),
 ('toddler groups','Toddler & preschool','["all"]'),
 ('stay and play','Toddler & preschool','["all"]'),
 ('preschool activities','Toddler & preschool','["all"]'),
 ('baby signing','Music & sensory','["all"]'),
 ('toddler music classes','Music & sensory','["all"]'),
 ('forest school under 5s','Outdoor & nature','["all"]'),
 ('parent and baby groups','Baby & pregnancy','["all"]');
