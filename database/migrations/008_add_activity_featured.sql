-- Bubba Hub migration 008: add the activity featured flag.
-- Safe for existing installations: add the column only when it is absent.
SET @bh_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='featured')=0,
  'ALTER TABLE bh_activities ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
  'SELECT 1'
);
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
