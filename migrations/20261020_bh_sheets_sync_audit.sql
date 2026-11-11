-- Run manually on live after backup and before enabling guarded writes.
-- Does not modify any existing activity rows.
CREATE TABLE IF NOT EXISTS bh_sheets_sync_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  listing_id BIGINT UNSIGNED NOT NULL,
  operation VARCHAR(64) NOT NULL,
  before_json LONGTEXT NOT NULL,
  after_json LONGTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
