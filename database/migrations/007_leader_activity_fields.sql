-- Leader portal activity fields
-- Apply to the intended Bubba Hub database after taking a backup.
-- Idempotent: each column is added only when it is not already present.
-- This migration is not executed by web requests.

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='accessibility') = 0,
  'ALTER TABLE bh_activities ADD COLUMN accessibility TEXT NULL',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='tags') = 0,
  'ALTER TABLE bh_activities ADD COLUMN tags TEXT NULL',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='weekly_schedule') = 0,
  'ALTER TABLE bh_activities ADD COLUMN weekly_schedule TEXT NULL',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='age_min_months') = 0,
  'ALTER TABLE bh_activities ADD COLUMN age_min_months INT NULL',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='age_max_months') = 0,
  'ALTER TABLE bh_activities ADD COLUMN age_max_months INT NULL',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='price_per_family') = 0,
  'ALTER TABLE bh_activities ADD COLUMN price_per_family TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='price_per_session') = 0,
  'ALTER TABLE bh_activities ADD COLUMN price_per_session TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='booking_required') = 0,
  'ALTER TABLE bh_activities ADD COLUMN booking_required TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='drop_in_welcome') = 0,
  'ALTER TABLE bh_activities ADD COLUMN drop_in_welcome TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='trial_available') = 0,
  'ALTER TABLE bh_activities ADD COLUMN trial_available TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='term_time_only') = 0,
  'ALTER TABLE bh_activities ADD COLUMN term_time_only TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='holiday_sessions') = 0,
  'ALTER TABLE bh_activities ADD COLUMN holiday_sessions TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='siblings_welcome') = 0,
  'ALTER TABLE bh_activities ADD COLUMN siblings_welcome TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='what_to_bring') = 0,
  'ALTER TABLE bh_activities ADD COLUMN what_to_bring VARCHAR(250) NULL',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='good_to_know') = 0,
  'ALTER TABLE bh_activities ADD COLUMN good_to_know VARCHAR(250) NULL',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='facebook_url') = 0,
  'ALTER TABLE bh_activities ADD COLUMN facebook_url VARCHAR(500) NULL',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='instagram_url') = 0,
  'ALTER TABLE bh_activities ADD COLUMN instagram_url VARCHAR(500) NULL',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

SET @bh_leader_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='gallery_images') = 0,
  'ALTER TABLE bh_activities ADD COLUMN gallery_images TEXT NULL',
  'SELECT 1'
);
PREPARE bh_leader_stmt FROM @bh_leader_sql;
EXECUTE bh_leader_stmt;
DEALLOCATE PREPARE bh_leader_stmt;

-- Server-side monthly import quotas (Free leader accounts only).
CREATE TABLE IF NOT EXISTS bh_listing_import_usage_v2 (
  user_id BIGINT UNSIGNED NOT NULL,
  listing_key VARCHAR(80) NOT NULL,
  import_type VARCHAR(16) NOT NULL,
  month_key CHAR(7) NOT NULL,
  used INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, listing_key, import_type, month_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
