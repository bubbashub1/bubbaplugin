-- Bubba Hub two-way Google Sheets sync (staging only; apply after DB review)
-- Existing directory tables are unchanged.
CREATE TABLE IF NOT EXISTS bh_sheet_sync_records (
  activity_id BIGINT UNSIGNED NOT NULL,
  sheet_row_id VARCHAR(100) NOT NULL,
  last_sheet_hash CHAR(64) DEFAULT NULL,
  last_site_hash CHAR(64) DEFAULT NULL,
  last_synced_at DATETIME DEFAULT NULL,
  PRIMARY KEY (activity_id),
  UNIQUE KEY uniq_sheet_row_id (sheet_row_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bh_sheet_sync_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME DEFAULT NULL,
  trigger_source VARCHAR(30) NOT NULL,
  status VARCHAR(30) NOT NULL,
  created_count INT NOT NULL DEFAULT 0,
  updated_count INT NOT NULL DEFAULT 0,
  skipped_count INT NOT NULL DEFAULT 0,
  conflict_count INT NOT NULL DEFAULT 0,
  error_message TEXT DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bh_sheet_sync_conflicts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  activity_id BIGINT UNSIGNED DEFAULT NULL,
  sheet_row_id VARCHAR(100) NOT NULL,
  site_hash CHAR(64) DEFAULT NULL,
  sheet_hash CHAR(64) DEFAULT NULL,
  detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  resolution VARCHAR(30) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY unresolved (resolved_at, detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
