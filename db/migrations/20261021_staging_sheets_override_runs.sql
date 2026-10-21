-- Staging only. Run after database backup and before enabling Sync Now.
CREATE TABLE IF NOT EXISTS bh_sheets_override_runs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 request_key CHAR(64) NOT NULL,
 admin_username VARCHAR(190) NOT NULL,
 activity_count INT UNSIGNED NOT NULL,
 change_count INT UNSIGNED NOT NULL,
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_request_key (request_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bh_sheets_override_changes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 run_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 field_name VARCHAR(32) NOT NULL,
 previous_value LONGTEXT NULL,
 new_value LONGTEXT NULL,
 previous_was_null TINYINT(1) NOT NULL DEFAULT 0,
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_run (run_id),
 KEY idx_activity (activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
