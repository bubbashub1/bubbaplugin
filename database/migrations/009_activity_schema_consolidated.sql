-- Bubba Hub consolidated activity schema migration (replaces PRs #789, #790 and #791).
-- Run once against the intended Bubba Hub database during a controlled deployment.
-- Each column is checked independently so an existing field is preserved.

SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='facebook_url')=0,'ALTER TABLE bh_activities ADD COLUMN facebook_url VARCHAR(2048) NULL DEFAULT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='instagram_url')=0,'ALTER TABLE bh_activities ADD COLUMN instagram_url VARCHAR(2048) NULL DEFAULT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_venues' AND COLUMN_NAME='booking_url')=0,'ALTER TABLE bh_venues ADD COLUMN booking_url VARCHAR(2048) NULL DEFAULT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='block_length_weeks')=0,'ALTER TABLE bh_activities ADD COLUMN block_length_weeks SMALLINT UNSIGNED NULL DEFAULT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='is_free')=0,'ALTER TABLE bh_activities ADD COLUMN is_free TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
SET @bh_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_sessions' AND COLUMN_NAME='class_name')=0,'ALTER TABLE bh_sessions ADD COLUMN class_name VARCHAR(160) NULL DEFAULT NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
