-- Listing Monitor website checks. Additive only; no public listing fields are changed.
CREATE TABLE IF NOT EXISTS bh_listing_monitor_website_state (
  activity_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  website_url VARCHAR(1000) NULL,
  last_checked_at DATETIME NULL,
  next_check_at DATETIME NULL,
  last_outcome VARCHAR(40) NULL,
  consecutive_dead_checks SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_monitor_website_due (next_check_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_listing_monitor_website_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  activity_id BIGINT UNSIGNED NOT NULL,
  website_url VARCHAR(1000) NOT NULL,
  outcome VARCHAR(40) NOT NULL,
  http_status SMALLINT UNSIGNED NULL,
  evidence MEDIUMTEXT NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_monitor_website_history_activity (activity_id, checked_at),
  KEY idx_monitor_website_history_outcome (outcome, checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
