-- Review and backup before applying. NOT executed by deployment.
-- Source snapshots are submitted only by the authenticated Master Sheet integration.
CREATE TABLE IF NOT EXISTS bh_sheets_sync_live_sheet_snapshots (
 activity_id BIGINT UNSIGNED NOT NULL,
 field_name VARCHAR(40) NOT NULL,
 sheet_value TEXT NOT NULL,
 received_at_utc DATETIME NOT NULL,
 source_run_id VARCHAR(100) NOT NULL,
 PRIMARY KEY(activity_id,field_name),
 KEY idx_received(received_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
