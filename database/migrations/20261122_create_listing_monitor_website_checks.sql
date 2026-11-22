-- Listing Monitor website history.
-- Existing bh_listing_check_findings and bh_listing_checker_settings are reused.
-- Do not share bh_listing_check_queue or bh_listing_check_runs: the legacy health
-- worker uses those tables to manage its own scheduling and interval calculations.
CREATE TABLE IF NOT EXISTS bh_listing_monitor_website_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  activity_id BIGINT UNSIGNED NOT NULL,
  website_url VARCHAR(1000) NOT NULL,
  outcome VARCHAR(40) NOT NULL,
  http_status SMALLINT UNSIGNED NULL,
  evidence MEDIUMTEXT NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_monitor_website_history_activity (activity_id, checked_at),
  KEY idx_monitor_website_history_url (activity_id, website_url(191), checked_at),
  KEY idx_monitor_website_history_outcome (outcome, checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO bh_listing_checker_settings (setting_key, setting_value) VALUES
  ('website_monitor_enabled','0'),
  ('website_monitor_paused','0'),
  ('website_monitor_interval_hours','4'),
  ('website_monitor_batch_size','30'),
  ('website_monitor_max_runtime_seconds','240');
