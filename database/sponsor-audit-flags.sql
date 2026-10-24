-- Apply to live database after backup, before enabling sponsor test classification.
CREATE TABLE IF NOT EXISTS `wp_bh_sponsor_audit_flags` (
  `entry_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  `is_test` TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- If your WordPress table prefix differs from wp_, adjust the table name accordingly.
