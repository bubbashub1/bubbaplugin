CREATE TABLE IF NOT EXISTS bh_sheets_override_batches (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 batch_key CHAR(64) NOT NULL,
 source_spreadsheet VARCHAR(255) NOT NULL,
 source_tab VARCHAR(128) NOT NULL,
 row_count INT UNSIGNED NOT NULL,
 rows_json LONGTEXT NOT NULL,
 status ENUM('prepared','applied','rejected') NOT NULL DEFAULT 'prepared',
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_batch_key (batch_key),
 KEY idx_status_created (status,created_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
