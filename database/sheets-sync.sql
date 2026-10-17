-- Staging migration only. Do not run against live until rollout approved.
CREATE TABLE IF NOT EXISTS bh_sheet_sync_state (
  activity_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  sheet_row_key VARCHAR(190) NOT NULL,
  base_json LONGTEXT NOT NULL,
  base_hash CHAR(64) NOT NULL,
  synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sheet_row_key (sheet_row_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bh_sheet_sync_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(30) NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  summary_json LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bh_sheet_sync_conflicts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  activity_id BIGINT UNSIGNED NOT NULL,
  field_name VARCHAR(100) NOT NULL,
  base_json LONGTEXT NULL,
  sheet_json LONGTEXT NULL,
  site_json LONGTEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pending (status,activity_id),
  KEY idx_run (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
